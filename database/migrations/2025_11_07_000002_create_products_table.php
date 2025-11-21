<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_type_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique(); // SKU/barcode
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->decimal('price_per_unit', 10, 2); // harga per ons atau per pcs
            $table->decimal('current_stock', 10, 2)->default(0); // stok dalam ons atau pcs
            $table->string('unit', 20); // ons, pcs
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};