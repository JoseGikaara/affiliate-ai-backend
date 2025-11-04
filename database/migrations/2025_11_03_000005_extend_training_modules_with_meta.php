<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->string('category')->nullable()->after('network_id');
            $table->string('preview_text', 500)->nullable()->after('content');
            $table->string('estimated_time')->nullable()->after('thumbnail_url');
            $table->string('difficulty')->nullable()->after('estimated_time');
        });
    }

    public function down(): void
    {
        Schema::table('training_modules', function (Blueprint $table) {
            $table->dropColumn(['category', 'preview_text', 'estimated_time', 'difficulty']);
        });
    }
};


