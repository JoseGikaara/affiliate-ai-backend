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
        Schema::create('gig_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gig_id')->constrained('user_gigs')->onDelete('cascade');
            $table->string('buyer_email');
            $table->text('requirements');
            $table->decimal('total_price', 10, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('paypal_transaction_id')->nullable();
            $table->timestamps();
            
            $table->index('gig_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gig_orders');
    }
};
