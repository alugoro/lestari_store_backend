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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['restock', 'sale', 'adjustment']); // tipe pergerakan stok
            $table->decimal('quantity', 10, 2); // jumlah perubahan (+ atau -)
            $table->decimal('stock_before', 10, 2); // stok sebelum
            $table->decimal('stock_after', 10, 2); // stok sesudah
            $table->string('reference_code')->nullable(); // kode transaksi atau PO
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};