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
        Schema::table('affiliate_networks', function (Blueprint $table) {
            $table->text('detailed_description')->nullable()->after('description');
            $table->string('registration_url')->nullable()->after('base_url');
            $table->string('commission_rate')->nullable()->after('category');
            $table->json('payment_methods')->nullable()->after('commission_rate');
            $table->string('minimum_payout')->nullable()->after('payment_methods');
            $table->string('payout_frequency')->nullable()->after('minimum_payout');
            $table->json('features')->nullable()->after('payout_frequency');
            $table->json('pros')->nullable()->after('features');
            $table->json('cons')->nullable()->after('pros');
            $table->integer('learn_more_credit_cost')->default(3)->after('cons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_networks', function (Blueprint $table) {
            $table->dropColumn([
                'detailed_description',
                'registration_url',
                'commission_rate',
                'payment_methods',
                'minimum_payout',
                'payout_frequency',
                'features',
                'pros',
                'cons',
                'learn_more_credit_cost',
            ]);
        });
    }
};
