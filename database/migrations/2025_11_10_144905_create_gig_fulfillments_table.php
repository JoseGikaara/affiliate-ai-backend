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
        Schema::create('gig_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('gig_orders')->onDelete('cascade');
            $table->longText('ai_output');
            $table->string('file_url')->nullable();
            $table->timestamps();
            
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gig_fulfillments');
    }
};
