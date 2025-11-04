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
        Schema::create('billing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('landing_page_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['publish', 'auto_renew', 'manual_renew', 'failure'])->default('publish');
            $table->integer('credits_deducted')->default(0);
            $table->enum('status', ['success', 'failed'])->default('success');
            $table->text('message')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index(['landing_page_id', 'created_at']);
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_logs');
    }
};
