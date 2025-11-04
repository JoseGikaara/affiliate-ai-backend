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
        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('payout_name')->nullable()->after('email');
            $table->string('payout_email')->nullable()->after('payout_name');
            $table->string('payout_phone')->nullable()->after('payout_email');
            $table->json('payout_account')->nullable()->after('payout_phone');
            $table->boolean('kyc_verified')->default(false)->after('payout_account');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropColumn(['payout_name', 'payout_email', 'payout_phone', 'payout_account', 'kyc_verified']);
        });
    }
};

