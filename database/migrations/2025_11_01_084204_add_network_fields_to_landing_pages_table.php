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
            $table->string('network')->nullable()->after('project_id');
            $table->text('affiliate_link')->nullable()->after('network');
            $table->integer('setup_credits')->default(5)->after('credit_cost');
            $table->integer('renewal_credits')->default(2)->after('setup_credits');
            $table->string('ai_template_type')->nullable()->after('type');
            $table->integer('credits_used')->default(0)->after('renewal_credits');
            
            $table->index('network');
            $table->index(['user_id', 'network']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'network']);
            $table->dropIndex(['network']);
            $table->dropColumn([
                'network',
                'affiliate_link',
                'setup_credits',
                'renewal_credits',
                'ai_template_type',
                'credits_used'
            ]);
        });
    }
};
