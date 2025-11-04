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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
            $table->foreignId('offer_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversion_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->decimal('payout_rate', 5, 2); // Rate used for calculation
            $table->decimal('conversion_value', 10, 2); // Original conversion value
            $table->date('date');
            $table->string('status')->default('pending'); // pending, approved, paid
            $table->timestamps();
            
            $table->index(['affiliate_id', 'date']);
            $table->index(['offer_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};

