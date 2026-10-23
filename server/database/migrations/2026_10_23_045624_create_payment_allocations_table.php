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
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();

            $table->decimal('allocated_amount', 15, 2);

            $table->timestamps();
            $table->unique(['payment_id', 'bill_id'], 'uq_payment_bill_allocation');

            $table->index('bill_id');
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
