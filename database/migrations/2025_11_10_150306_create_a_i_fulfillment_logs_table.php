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
        Schema::create('a_i_fulfillment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('dropservicing_orders')->onDelete('cascade');
            $table->foreignId('marketing_plan_id')->nullable()->constrained('dropservicing_marketing_plans')->onDelete('cascade');
            $table->string('ai_model')->default('gpt-4o-mini');
            $table->integer('tokens_used')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('marketing_plan_id');
            $table->index('success');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_i_fulfillment_logs');
    }
};
