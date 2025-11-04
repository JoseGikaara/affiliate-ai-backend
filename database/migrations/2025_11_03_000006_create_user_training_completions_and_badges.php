<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_training_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('training_id');
            $table->timestamps();
            $table->unique(['user_id', 'training_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('training_id')->references('id')->on('training_modules')->onDelete('cascade');
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('badge_code'); // e.g., beginner, intermediate, pro_marketer, affiliate_guru
            $table->timestamps();
            $table->unique(['user_id', 'badge_code']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_training_completions');
        Schema::dropIfExists('user_badges');
    }
};


