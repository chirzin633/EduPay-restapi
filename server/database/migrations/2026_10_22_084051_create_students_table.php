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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_id_internal', 50)->unique();
            $table->string('nis', 30)->nullable()->unique();
            $table->string('nisn', 30)->nullable()->unique();
            $table->string('full_name', 150);
            $table->string('gender', 10);
            $table->date('birth_date')->nullable();
            $table->string('parent_name', 150)->nullable();
            $table->string('parent_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'inactive', 'graduated', 'transferred', 'dropped_out'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
            $table->index('status');
            $table->index(['nis', 'nisn'], 'idx_students_nis_nisn');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
