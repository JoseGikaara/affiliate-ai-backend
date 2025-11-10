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
        Schema::create('user_gigs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->json('pricing_tiers');
            $table->string('paypal_email');
            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft');
            $table->string('slug')->unique();
            $table->timestamp('last_renewed_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('slug');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_gigs');
    }
};
