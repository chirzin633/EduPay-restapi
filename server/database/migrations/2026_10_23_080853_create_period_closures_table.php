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
        Schema::create('period_closures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period_label', 30);
            $table->enum('period_type', ['monthly', 'semester', 'academic_year'])->default('monthly');

            $table->timestampTz('closed_at');
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->text('closure_reason')->nullable();

            $table->boolean('is_reopened')->default(false);
            $table->timestampTz('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopening_reason');

            $table->decimal('total_bills_amount', 15, 2)->default(0);
            $table->decimal('total_paid_amount', 15, 2)->default(0);
            $table->decimal('outstanding_amount', 15, 2)->default(0);
            $table->integer('total_transactions_count')->default(0);

            $table->timestamps();

            $table->unique(['academic_year_id', 'semester_id', 'period_label'], 'uq_period_closure');

            $table->index(['academic_year_id', 'period_type']);
            $table->index('is_reopened');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('period_closures');
    }
};
