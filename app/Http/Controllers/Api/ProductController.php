<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Get all products dengan filter & search
     */
    public function index(Request $request)
    {
        // Normalize and validate filter parameters
        $isActiveValue = null;
        if ($request->filled('is_active')) {
            $isActiveValue = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }
        
        $request->merge([
            'product_type_id' => $request->filled('product_type_id') ? $request->product_type_id : null,
            'is_active' => $isActiveValue,
            'search' => $request->filled('search') ? $request->search : null,
            'per_page' => $request->filled('per_page') ? (int) $request->per_page : null,
        ]);

        $validator = Validator::make($request->all(), [
            'product_type_id' => 'nullable|exists:product_types,id',
            'is_active' => 'nullable|boolean',
            'search' => 'nullable|string',
            'sort_by' => 'nullable|in:name,code,price_per_unit,current_stock,created_at',
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
        $query = Product::with('productType');

        // Filter by product type
        if (!empty($filters['product_type_id'] ?? null)) {
            $query->where('product_type_id', $filters['product_type_id']);
        }

        // Filter by active status
        if (!is_null($filters['is_active'] ?? null)) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Search by name or code
        if (!empty($filters['search'] ?? null)) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Sort by
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products
        ], 200);
    }

    /**
     * Get single product by ID
     */
    public function show($id)
    {
        $product = Product::with('productType')->find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ], 200);
    }

    /**
     * Create new product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_type_id' => 'required|exists:product_types,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:products,code',
            'description' => 'nullable|string',
            'price_per_unit' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_stock' => 'nullable|numeric|min:0',
            'unit' => 'required|string|in:ons,pcs',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // max 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('products', $imageName, 'public');
            $data['image_url'] = '/storage/' . $imagePath;
        }

        $product = Product::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product->load('productType')
        ], 201);
    }

    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'product_type_id' => 'sometimes|exists:product_types,id',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:products,code,' . $id,
            'description' => 'nullable|string',
            'price_per_unit' => 'sometimes|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'current_stock' => 'sometimes|numeric|min:0',
            'unit' => 'sometimes|string|in:ons,pcs',
            'is_active' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($product->image_url) {
                $oldImagePath = str_replace('/storage/', '', $product->image_url);
                Storage::disk('public')->delete($oldImagePath);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('products', $imageName, 'public');
            $data['image_url'] = '/storage/' . $imagePath;
        }

        $product->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil diupdate',
            'data' => $product->load('productType')
        ], 200);
    }

    /**
     * Delete product
     */
    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        // Delete image
        if ($product->image_url) {
            $imagePath = str_replace('/storage/', '', $product->image_url);
            Storage::disk('public')->delete($imagePath);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Produk berhasil dihapus'
        ], 200);
    }

    /**
     * Toggle product active status
     */
    public function toggleStatus($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Produk tidak ditemukan'
            ], 404);
        }

        $product->is_active = !$product->is_active;
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Status produk berhasil diubah',
            'data' => $product
        ], 200);
    }
}