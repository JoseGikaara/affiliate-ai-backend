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
        if (!Schema::hasTable('landing_page_analytics')) {
            Schema::create('landing_page_analytics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('landing_page_id')->constrained()->cascadeOnDelete();
                $table->string('event_type'); // 'view' or 'conversion'
                $table->string('ip_address', 45); // Support IPv6
                $table->string('user_agent')->nullable();
                $table->string('referer')->nullable();
                $table->json('metadata')->nullable(); // Additional tracking data
                $table->timestamps();
                
                // Index for IP-based deduplication queries (short names for MySQL)
                $table->index(['landing_page_id', 'event_type', 'ip_address', 'created_at'], 'lp_analytics_dedup_idx');
                $table->index(['landing_page_id', 'created_at'], 'lp_analytics_page_date_idx');
            });
        } else {
            // Table exists, add indexes if they don't exist using raw SQL
            $connection = Schema::getConnection();
            $dbName = $connection->getDatabaseName();
            
            // Check and add first index
            $index1Exists = $connection->selectOne("
                SELECT COUNT(*) as count 
                FROM information_schema.statistics 
                WHERE table_schema = ? 
                AND table_name = 'landing_page_analytics' 
                AND index_name = 'lp_analytics_dedup_idx'
            ", [$dbName]);
            
            if (!$index1Exists || $index1Exists->count == 0) {
                $connection->statement("
                    CREATE INDEX lp_analytics_dedup_idx 
                    ON landing_page_analytics (landing_page_id, event_type, ip_address, created_at)
                ");
            }
            
            // Check and add second index
            $index2Exists = $connection->selectOne("
                SELECT COUNT(*) as count 
                FROM information_schema.statistics 
                WHERE table_schema = ? 
                AND table_name = 'landing_page_analytics' 
                AND index_name = 'lp_analytics_page_date_idx'
            ", [$dbName]);
            
            if (!$index2Exists || $index2Exists->count == 0) {
                $connection->statement("
                    CREATE INDEX lp_analytics_page_date_idx 
                    ON landing_page_analytics (landing_page_id, created_at)
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_analytics');
    }
};
