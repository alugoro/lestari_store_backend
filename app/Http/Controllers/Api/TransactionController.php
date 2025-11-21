<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class TransactionController extends Controller
{
    /**
     * Get all transactions dengan filter
     */
    public function index(Request $request)
    {
        $request->merge([
            'start_date' => $request->filled('start_date') ? $request->start_date : null,
            'end_date' => $request->filled('end_date') ? $request->end_date : null,
            'user_id' => $request->filled('user_id') ? $request->user_id : null,
            'payment_method' => $request->filled('payment_method') ? $request->payment_method : null,
            'today' => $request->has('today') ? $request->boolean('today') : null,
            'per_page' => $request->filled('per_page') ? (int) $request->per_page : null,
        ]);

        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'today' => 'nullable|boolean',
            'user_id' => 'nullable|exists:users,id',
            'payment_method' => 'nullable|in:cash,transfer',
            'search' => 'nullable|string',
            'sort_by' => 'nullable|in:transaction_date,transaction_code,total_amount,total_profit',
            'sort_order' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $filters = $validator->validated();

        $query = Transaction::with(['user:id,name,email', 'items.product']);

        // Filter by date range
        if (!empty($filters['start_date'] ?? null)) {
            $query->whereDate('transaction_date', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'] ?? null)) {
            $query->whereDate('transaction_date', '<=', $filters['end_date']);
        }

        // Filter by today
        if ($request->boolean('today')) {
            $query->whereDate('transaction_date', Carbon::today());
        }

        // Filter by user (kasir)
        if (!empty($filters['user_id'] ?? null)) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by payment method
        if (!empty($filters['payment_method'] ?? null)) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Search by transaction code
        if (!empty($filters['search'] ?? null)) {
            $query->where('transaction_code', 'like', '%' . $filters['search'] . '%');
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'transaction_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ], 200);
    }

    /**
     * Get single transaction detail
     */
    public function show($id)
    {
        $transaction = Transaction::with(['user:id,name,email', 'items.product.productType'])
            ->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaksi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction
        ], 200);
    }

    /**
     * Create new transaction (Checkout)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.notes' => 'nullable|string',
            'payment_method' => 'required|in:cash,transfer',
            'paid_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ], [
            'items.required' => 'Item transaksi harus diisi',
            'items.min' => 'Minimal 1 item',
            'items.*.product_id.required' => 'Produk harus dipilih',
            'items.*.product_id.exists' => 'Produk tidak valid',
            'items.*.quantity.required' => 'Quantity harus diisi',
            'items.*.quantity.min' => 'Quantity minimal 0.01',
            'payment_method.required' => 'Metode pembayaran harus dipilih',
            'payment_method.in' => 'Metode pembayaran tidak valid',
            'paid_amount.required' => 'Jumlah bayar harus diisi',
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
            // Calculate total amount & total profit
            $totalAmount = 0;
            $totalProfit = 0;
            $transactionItems = [];

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Produk ID {$item['product_id']} tidak ditemukan");
                }

                if (!$product->is_active) {
                    throw new \Exception("Produk {$product->name} tidak aktif");
                }

                // Cek stok
                if ($product->current_stock < $item['quantity']) {
                    throw new \Exception("Stok {$product->name} tidak mencukupi. Stok tersedia: {$product->current_stock} {$product->unit}");
                }

                $subtotal = $product->price_per_unit * $item['quantity'];
                $purchasePrice = $product->purchase_price ?? 0;
                $itemProfit = ($product->price_per_unit - $purchasePrice) * $item['quantity'];
                
                $totalAmount += $subtotal;
                $totalProfit += $itemProfit;

                $transactionItems[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price_per_unit,
                    'purchase_price' => $purchasePrice,
                    'subtotal' => $subtotal,
                    'profit' => $itemProfit,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            // Cek apakah uang cukup
            if ($request->paid_amount < $totalAmount) {
                throw new \Exception("Uang tidak cukup. Total: Rp " . number_format($totalAmount, 0, ',', '.'));
            }

            $changeAmount = $request->paid_amount - $totalAmount;

            // Generate transaction code
            $transactionCode = $this->generateTransactionCode();

            // Create transaction
            $transaction = Transaction::create([
                'transaction_code' => $transactionCode,
                'user_id' => $request->user()->id,
                'total_amount' => $totalAmount,
                'total_profit' => $totalProfit,
                'paid_amount' => $request->paid_amount,
                'change_amount' => $changeAmount,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
                'transaction_date' => now(),
            ]);

            // Create transaction items & update stock
            foreach ($transactionItems as $itemData) {
                // Create transaction item
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'purchase_price' => $itemData['purchase_price'],
                    'subtotal' => $itemData['subtotal'],
                    'profit' => $itemData['profit'],
                    'notes' => $itemData['notes'],
                ]);

                // Update stock
                $product = $itemData['product'];
                $stockBefore = $product->current_stock;
                $product->current_stock -= $itemData['quantity'];
                $product->save();

                // Record stock movement
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $request->user()->id,
                    'type' => 'sale',
                    'quantity' => -$itemData['quantity'], // negatif untuk pengurangan
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->current_stock,
                    'reference_code' => $transactionCode,
                    'notes' => 'Penjualan via transaksi ' . $transactionCode,
                ]);
            }

            DB::commit();

            // Load relationships untuk response
            $transaction->load(['user:id,name,email', 'items.product']);

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil',
                'data' => $transaction
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Transaksi gagal: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate unique transaction code
     * Format: TRX-YYYYMMDD-XXX
     */
    private function generateTransactionCode()
    {
        $date = Carbon::now()->format('Ymd');
        $prefix = 'TRX-' . $date . '-';

        // Get last transaction today
        $lastTransaction = Transaction::where('transaction_code', 'like', $prefix . '%')
            ->orderBy('transaction_code', 'desc')
            ->first();

        if ($lastTransaction) {
            // Extract number and increment
            $lastNumber = (int) substr($lastTransaction->transaction_code, -3);
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return $prefix . $newNumber;
    }

    /**
     * Get today's transaction summary
     */
    public function todaySummary(Request $request)
    {
        $today = Carbon::today();

        $summary = [
            'total_transactions' => Transaction::whereDate('transaction_date', $today)->count(),
            'total_sales' => Transaction::whereDate('transaction_date', $today)->sum('total_amount'),
            'total_profit' => Transaction::whereDate('transaction_date', $today)->sum('total_profit'),
            'cash_sales' => Transaction::whereDate('transaction_date', $today)
                ->where('payment_method', 'cash')
                ->sum('total_amount'),
            'transfer_sales' => Transaction::whereDate('transaction_date', $today)
                ->where('payment_method', 'transfer')
                ->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ], 200);
    }
}