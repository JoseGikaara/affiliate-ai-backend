<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->string('origin')->nullable()->after('type'); // 'free' or 'paid'
            $table->string('locked_for')->nullable()->after('origin'); // e.g., 'training'
        });
    }

    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn(['origin', 'locked_for']);
        });
    }
};


