<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    /**
     * Get all purchases dengan filter
     */
    public function index(Request $request)
    {
        $query = Purchase::with(['user:id,name,email', 'items.product']);

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('purchase_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('purchase_date', '<=', $request->end_date);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by purchase code or supplier
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('purchase_code', 'like', "%{$search}%")
                  ->orWhere('supplier_name', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'purchase_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $purchases = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $purchases
        ], 200);
    }

    /**
     * Get single purchase detail
     */
    public function show($id)
    {
        $purchase = Purchase::with(['user:id,name,email', 'items.product.productType'])
            ->find($id);

        if (!$purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Pembelian tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $purchase
        ], 200);
    }

    /**
     * Create new purchase (Restock)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.purchase_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'supplier_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ], [
            'items.required' => 'Item pembelian harus diisi',
            'items.min' => 'Minimal 1 item',
            'items.*.product_id.required' => 'Produk harus dipilih',
            'items.*.product_id.exists' => 'Produk tidak valid',
            'items.*.quantity.required' => 'Quantity harus diisi',
            'items.*.quantity.min' => 'Quantity minimal 0.01',
            'items.*.purchase_price.required' => 'Harga beli harus diisi',
            'items.*.purchase_price.min' => 'Harga beli minimal 0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Calculate total amount
            $totalAmount = 0;
            $purchaseItems = [];

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Produk ID {$item['product_id']} tidak ditemukan");
                }

                $subtotal = $item['purchase_price'] * $item['quantity'];
                $totalAmount += $subtotal;

                $purchaseItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'purchase_price' => $item['purchase_price'],
                    'subtotal' => $subtotal,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            // Generate purchase code
            $purchaseCode = $this->generatePurchaseCode();

            // Create purchase
            $purchase = Purchase::create([
                'purchase_code' => $purchaseCode,
                'user_id' => $request->user()->id,
                'supplier_name' => $request->supplier_name,
                'total_amount' => $totalAmount,
                'status' => 'completed',
                'notes' => $request->notes,
                'purchase_date' => now(),
            ]);

            // Create purchase items & update stock
            foreach ($purchaseItems as $itemData) {
                // Create purchase item
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'purchase_price' => $itemData['purchase_price'],
                    'subtotal' => $itemData['subtotal'],
                    'notes' => $itemData['notes'],
                ]);

                // Update stock & purchase price dengan Weighted Average Cost
                $product = $itemData['product'];
                $stockBefore = $product->current_stock;
                $oldPurchasePrice = $product->purchase_price ?? 0;
                
                // Calculate Weighted Average Cost (WAC)
                // Formula: ((old_stock × old_price) + (new_stock × new_price)) / (old_stock + new_stock)
                $oldTotalValue = $stockBefore * $oldPurchasePrice;
                $newTotalValue = $itemData['quantity'] * $itemData['purchase_price'];
                $totalStock = $stockBefore + $itemData['quantity'];
                
                // WAC calculation
                if ($totalStock > 0) {
                    $weightedAverageCost = ($oldTotalValue + $newTotalValue) / $totalStock;
                } else {
                    $weightedAverageCost = $itemData['purchase_price'];
                }
                
                $product->current_stock = $totalStock;
                $product->purchase_price = round($weightedAverageCost, 2); // Round ke 2 desimal
                $product->save();

                // Record stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'type' => 'restock',
                    'quantity' => $itemData['quantity'], // positif untuk penambahan
                    'purchase_price' => $itemData['purchase_price'], // harga beli batch ini
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->current_stock,
                    'reference_code' => $purchaseCode,
                    'notes' => sprintf(
                        'Restock via %s. Harga beli batch: Rp %s. WAC baru: Rp %s',
                        $purchaseCode,
                        number_format($itemData['purchase_price'], 0, ',', '.'),
                        number_format($product->purchase_price, 0, ',', '.')
                    ),
                ]);
            }

            DB::commit();

            // Load relationships untuk response
            $purchase->load(['user:id,name,email', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Pembelian berhasil dicatat',
                'data' => $purchase
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Pembelian gagal: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate unique purchase code
     * Format: PUR-YYYYMMDD-XXX
     */
    private function generatePurchaseCode()
    {
        $date = Carbon::now()->format('Ymd');
        $prefix = 'PUR-' . $date . '-';

        // Get last purchase today
        $lastPurchase = Purchase::where('purchase_code', 'like', $prefix . '%')
            ->orderBy('purchase_code', 'desc')
            ->first();

        if ($lastPurchase) {
            // Extract number and increment
            $lastNumber = (int) substr($lastPurchase->purchase_code, -3);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return $prefix . $newNumber;
    }

    /**
     * Calculate Weighted Average Cost
     * 
     * @param float $oldStock - Stok lama
     * @param float $oldPrice - Harga beli lama
     * @param float $newStock - Stok baru
     * @param float $newPrice - Harga beli baru
     * @return float - WAC result
     */
    private function calculateWeightedAverageCost($oldStock, $oldPrice, $newStock, $newPrice)
    {
        $oldTotalValue = $oldStock * $oldPrice;
        $newTotalValue = $newStock * $newPrice;
        $totalStock = $oldStock + $newStock;
        
        if ($totalStock > 0) {
            return ($oldTotalValue + $newTotalValue) / $totalStock;
        }
        
        return $newPrice;
    }

    /**
     * Delete purchase (hanya jika status pending)
     */
    public function destroy($id)
    {
        $purchase = Purchase::find($id);

        if (!$purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Pembelian tidak ditemukan'
            ], 404);
        }

        if ($purchase->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Pembelian yang sudah completed tidak dapat dihapus'
            ], 400);
        }

        $purchase->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pembelian berhasil dihapus'
        ], 200);
    }
}