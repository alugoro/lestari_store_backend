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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_code')->unique(); // PUR20241107001
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('supplier_name')->nullable();
            $table->decimal('total_amount', 12, 2); // total pembelian
            $table->enum('status', ['pending', 'completed'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamp('purchase_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};