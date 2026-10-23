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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->string('refund_number', 50)->unique();

            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->decimal('refund_amount', 15, 2);
            $table->text('reason');
            $table->enum('refund_method', ['cash', 'bank_transfer', 'qris'])->default('bank_transfer');
            $table->string('recipient_account_name', 150)->nullable();
            $table->string('recipient_account_number', 100)->nullable();
            $table->string('attachment_path', 255)->nullable();

            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'rejected',
                'processed',
                'completed'
            ])->default('draft');

            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();

            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->string('completion_reference', 100)->nullable();
            $table->text('completion_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('payment_id');
            $table->index('requested_by');
            $table->index('refund_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
