<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockMovementController extends Controller
{
    /**
     * Get all stock movements dengan filter
     */
    public function index(Request $request)
    {
        $query = StockMovement::with(['product:id,name,code,unit', 'user:id,name,email']);

        // Filter by product
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Search by reference code
        if ($request->has('search')) {
            $query->where('reference_code', 'like', '%' . $request->search . '%');
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $movements = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $movements
        ], 200);
    }

    /**
     * Stock Adjustment (owner/admin only)
     * Untuk koreksi manual stok (misalnya produk rusak, hilang, dll)
     */
    public function adjustment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|not_in:0',
            'notes' => 'required|string',
        ], [
            'product_id.required' => 'Produk harus dipilih',
            'product_id.exists' => 'Produk tidak valid',
            'quantity.required' => 'Quantity harus diisi',
            'quantity.not_in' => 'Quantity tidak boleh 0',
            'notes.required' => 'Catatan/alasan adjustment harus diisi',
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
            $product = Product::find($request->product_id);

            if (!$product) {
                throw new \Exception("Produk tidak ditemukan");
            }

            $stockBefore = $product->current_stock;
            $newStock = $stockBefore + $request->quantity;

            // Cek stok tidak boleh negatif
            if ($newStock < 0) {
                throw new \Exception("Stok tidak boleh negatif. Stok saat ini: {$stockBefore} {$product->unit}");
            }

            // Update stock
            $product->current_stock = $newStock;
            $product->save();

            // Record stock movement
            $movement = StockMovement::create([
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
                'type' => 'adjustment',
                'quantity' => $request->quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $newStock,
                'reference_code' => 'ADJ-' . time(),
                'notes' => $request->notes,
            ]);

            DB::commit();

            $movement->load(['product', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Stock adjustment berhasil',
                'data' => $movement
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment gagal: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get stock movement history per product
     */
    public function productHistory($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        $movements = StockMovement::with('user:id,name,email')
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'movements' => $movements,
            ]
        ], 200);
    }

    /**
     * Get products with low stock
     */
    public function lowStock(Request $request)
    {
        $threshold = $request->get('threshold', 10); // default threshold 10

        $products = Product::with('productType')
            ->where('is_active', true)
            ->where('current_stock', '<=', $threshold)
            ->orderBy('current_stock', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ], 200);
    }
}