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
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->decimal('total_sales', 12, 2)->default(0); // total penjualan
            $table->decimal('total_profit', 12, 2)->default(0); // total keuntungan
            $table->decimal('cash_amount', 12, 2)->default(0); // total cash
            $table->decimal('transfer_amount', 12, 2)->default(0); // total transfer
            $table->integer('transaction_count')->default(0); // jumlah transaksi
            $table->json('top_products')->nullable(); // produk terlaris hari itu
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
    }
};