<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductType;

class ProductTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Timbangan',
                'description' => 'Produk snack yang dijual berdasarkan berat (ons)',
            ],
            [
                'name' => 'Kemasan',
                'description' => 'Produk snack yang sudah dikemas dengan harga tetap per kemasan',
            ],
        ];

        foreach ($types as $type) {
            ProductType::create($type);
        }
    }
}