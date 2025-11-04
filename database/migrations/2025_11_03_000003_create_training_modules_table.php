<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('training_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('network_id');
            $table->string('title');
            $table->text('content');
            $table->string('thumbnail_url')->nullable();
            $table->unsignedInteger('credit_cost')->default(5);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->foreign('network_id')->references('id')->on('affiliate_networks')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_modules');
    }
};


