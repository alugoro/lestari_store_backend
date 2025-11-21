<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductType;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timbangan = ProductType::where('name', 'Timbangan')->first();
        $kemasan = ProductType::where('name', 'Kemasan')->first();

        // Produk Timbangan (harga per ons)
        $productsTimbangan = [
            [
                'product_type_id' => $timbangan->id,
                'name' => 'Keripik Singkong Original',
                'code' => 'SNK-TIM-001',
                'description' => 'Keripik singkong renyah dengan rasa original',
                'price_per_unit' => 3500, // harga jual per ons
                'purchase_price' => 2500, // harga beli per ons
                'current_stock' => 500, // dalam ons
                'unit' => 'ons',
                'is_active' => true,
            ],
            [
                'product_type_id' => $timbangan->id,
                'name' => 'Keripik Singkong Balado',
                'code' => 'SNK-TIM-002',
                'description' => 'Keripik singkong pedas balado',
                'price_per_unit' => 4000,
                'purchase_price' => 3000,
                'current_stock' => 300,
                'unit' => 'ons',
                'is_active' => true,
            ],
            [
                'product_type_id' => $timbangan->id,
                'name' => 'Kacang Telur',
                'code' => 'SNK-TIM-003',
                'description' => 'Kacang tanah dibalut tepung telur',
                'price_per_unit' => 5000,
                'purchase_price' => 3500,
                'current_stock' => 200,
                'unit' => 'ons',
                'is_active' => true,
            ],
        ];

        // Produk Kemasan (harga per pcs)
        $productsKemasan = [
            [
                'product_type_id' => $kemasan->id,
                'name' => 'Chitato Rasa Sapi Panggang 68g',
                'code' => 'SNK-KEM-001',
                'description' => 'Keripik kentang Chitato kemasan 68g',
                'price_per_unit' => 12000, // per kemasan
                'current_stock' => 50, // jumlah kemasan
                'unit' => 'pcs',
                'is_active' => true,
            ],
            [
                'product_type_id' => $kemasan->id,
                'name' => 'Oreo Original 137g',
                'code' => 'SNK-KEM-002',
                'description' => 'Biskuit Oreo vanilla kemasan 137g',
                'price_per_unit' => 15000,
                'current_stock' => 30,
                'unit' => 'pcs',
                'is_active' => true,
            ],
            [
                'product_type_id' => $kemasan->id,
                'name' => 'Taro Net 40g',
                'code' => 'SNK-KEM-003',
                'description' => 'Keripik kentang Taro kemasan 40g',
                'price_per_unit' => 8000,
                'current_stock' => 80,
                'unit' => 'pcs',
                'is_active' => true,
            ],
        ];

        // Insert semua produk
        foreach (array_merge($productsTimbangan, $productsKemasan) as $product) {
            Product::create($product);
        }
    }
}