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
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->default(0)->after('unit_price'); // harga beli saat transaksi
            $table->decimal('profit', 12, 2)->default(0)->after('subtotal'); // keuntungan per item
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn(['purchase_price', 'profit']);
        });
    }
};