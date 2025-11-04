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
        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->onDelete('cascade');
            $table->foreignId('offer_id')->constrained()->onDelete('cascade');
            $table->foreignId('affiliate_link_id')->nullable()->constrained()->onDelete('set null');
            $table->string('tracking_id', 100)->nullable()->index();
            $table->decimal('conversion_value', 10, 2);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('metadata')->nullable(); // JSON for additional data
            $table->timestamps();
            
            $table->index(['affiliate_id', 'offer_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
};

