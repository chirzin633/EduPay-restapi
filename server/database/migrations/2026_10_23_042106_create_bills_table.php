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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 50)->unique();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_name_snapshopt', 150);
            $table->string('class_name_snapshot', 50)->nullable();
            $table->string('grade_level_snapshot', 30)->nullable();
            $table->string('period_label', 30);
            $table->date('due_date');
            $table->decimal('original_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->text('discount_reason')->nullable();
            $table->foreignId('discount_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'unpaid', 'partial', 'paid', 'canceled'])->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['student_id', 'bill_type_id', 'period_label', 'academic_year_id'], 'uq_bill_student_period');

            $table->index('status');
            $table->index('due_date');
            $table->index(['student_id', 'status']);
            $table->index(['academic_year_id', 'semester_id']);
            $table->index('bill_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
