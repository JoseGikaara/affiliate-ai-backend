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
        Schema::create('marketing_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['ad_copy', 'email', 'social_post'])->default('ad_copy');
            $table->text('content');
            $table->json('meta')->nullable(); // Additional metadata
            $table->timestamps();
            
            $table->index(['landing_page_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_assets');
    }
};
