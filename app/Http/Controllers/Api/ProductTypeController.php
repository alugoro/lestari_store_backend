<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductType;
use Illuminate\Http\Request;

class ProductTypeController extends Controller
{
    /**
     * Get all product types
     */
    public function index()
    {
        $productTypes = ProductType::withCount('products')->get();

        return response()->json([
            'success' => true,
            'data' => $productTypes
        ], 200);
    }

    /**
     * Get single product type with products
     */
    public function show($id)
    {
        $productType = ProductType::with('products')->find($id);

        if (!$productType) {
            return response()->json([
                'success' => false,
                'message' => 'Tipe produk tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $productType
        ], 200);
    }
}