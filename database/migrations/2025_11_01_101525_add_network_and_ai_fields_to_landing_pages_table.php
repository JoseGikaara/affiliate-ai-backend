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
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->foreignId('affiliate_network_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->text('html_content')->nullable()->after('content'); // AI-generated HTML content
            $table->text('ad_copy')->nullable()->after('html_content'); // AI-generated ad copy
            $table->json('email_series')->nullable()->after('ad_copy'); // AI-generated email follow-ups
            $table->string('campaign_goal')->nullable()->after('email_series'); // sales, signups, leads
            
            $table->index('affiliate_network_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropForeign(['affiliate_network_id']);
            $table->dropColumn([
                'affiliate_network_id',
                'html_content',
                'ad_copy',
                'email_series',
                'campaign_goal',
            ]);
        });
    }
};
