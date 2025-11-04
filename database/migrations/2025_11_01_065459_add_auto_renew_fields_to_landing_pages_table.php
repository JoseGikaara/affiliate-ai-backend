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
            $table->boolean('auto_renew')->default(true)->after('status');
            $table->timestamp('next_renewal_date')->nullable()->after('expires_at');
            $table->timestamp('last_renewal_date')->nullable()->after('next_renewal_date');
            
            $table->index('next_renewal_date');
            $table->index(['auto_renew', 'next_renewal_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropIndex(['auto_renew', 'next_renewal_date']);
            $table->dropIndex(['next_renewal_date']);
            $table->dropColumn(['auto_renew', 'next_renewal_date', 'last_renewal_date']);
        });
    }
};
