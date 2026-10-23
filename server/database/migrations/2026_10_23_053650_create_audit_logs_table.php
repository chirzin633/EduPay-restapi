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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name_snapshot', 150)->nullable();
            $table->timestampTz('action_at');

            $table->string('action', 50);
            $table->string('subject_type', 200);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255)->nullable();

            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 500)->nullable();

            $table->index('user_id');
            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
            $table->index('action_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
