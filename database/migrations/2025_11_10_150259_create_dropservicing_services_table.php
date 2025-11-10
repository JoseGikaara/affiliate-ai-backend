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
        Schema::create('dropservicing_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->integer('credit_cost');
            $table->text('ai_prompt_template'); // JSON or text template
            $table->string('delivery_time')->default('24 hours');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('input_fields')->nullable(); // Define required input fields
            $table->timestamps();
            
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropservicing_services');
    }
};
