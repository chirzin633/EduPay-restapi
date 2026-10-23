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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 20)->unique();

            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('payment_method', ['cash', 'bank_transfer', 'qris'])->default('cash');
            $table->decimal('total_amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('attachment_path', 255)->nullable();

            $table->enum('status', ['pending_verification', 'verified', 'rejected', 'cancelled'])->default('pending_verification');

            $table->timestampTz('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('payment_date');
            $table->index(['student_id', 'status']);
            $table->index('payment_number');
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
