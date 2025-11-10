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
        Schema::create('dropservicing_marketing_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('gig_id')->nullable()->constrained('user_gigs')->onDelete('cascade');
            $table->enum('plan_type', ['7-day', '30-day', 'ads-only', 'content-calendar']);
            $table->json('input_summary'); // audience, platforms, budget, goals, tone, etc.
            $table->longText('ai_output')->nullable();
            $table->integer('credit_cost');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->integer('tokens_used')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('gig_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropservicing_marketing_plans');
    }
};
