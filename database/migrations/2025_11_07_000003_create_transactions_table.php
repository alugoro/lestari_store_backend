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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->unique(); // TRX20241107001
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // kasir yang input
            $table->decimal('total_amount', 12, 2); // total belanja
            $table->decimal('paid_amount', 12, 2); // uang yang dibayar
            $table->decimal('change_amount', 12, 2)->default(0); // kembalian
            $table->enum('payment_method', ['cash', 'transfer'])->default('cash');
            $table->text('notes')->nullable();
            $table->timestamp('transaction_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};