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
        Schema::create('bill_type_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_type_id')->constrained()->restrictOnDelete();
            $table->string('grade_level', 30)->nullable();
            $table->string('class_name', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bill_type_id', 'grade_level', 'class_name'], 'uq_bill_type_tariff');
            $table->index('bill_type_id', 'is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_type_tariffs');
    }
};
