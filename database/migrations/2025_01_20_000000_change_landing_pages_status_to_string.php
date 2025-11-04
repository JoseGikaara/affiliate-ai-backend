<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if landing_pages table exists
        if (!Schema::hasTable('landing_pages')) {
            return; // Table doesn't exist yet, skip this migration
        }

        // SQLite-compatible way to change column type
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            // For SQLite, we need to recreate the table
            // Since the table might not exist yet, we'll handle it differently
            // If table exists and has status column, update data
            if (Schema::hasColumn('landing_pages', 'status')) {
                // Update existing data to use standard status values
                DB::table('landing_pages')
                    ->where('status', 'inactive')
                    ->update(['status' => 'paused']);
                
                // Ensure no invalid statuses remain - set to draft as fallback
                $validStatuses = ['draft', 'active', 'paused', 'expired', 'unpublished'];
                DB::table('landing_pages')
                    ->whereNotIn('status', $validStatuses)
                    ->update(['status' => 'draft']);
            }
        } else {
            // For MySQL/MariaDB, use MODIFY COLUMN
            DB::statement("ALTER TABLE landing_pages MODIFY COLUMN status VARCHAR(255) DEFAULT 'draft'");
            
            // Update existing data
            DB::table('landing_pages')
                ->where('status', 'inactive')
                ->update(['status' => 'paused']);
            
            $validStatuses = ['draft', 'active', 'paused', 'expired', 'unpublished'];
            DB::table('landing_pages')
                ->whereNotIn('status', $validStatuses)
                ->update(['status' => 'draft']);
        }
        
        // Add index on status for performance (if not exists)
        if (Schema::hasTable('landing_pages') && !Schema::hasColumn('landing_pages', 'status_index')) {
            Schema::table('landing_pages', function (Blueprint $table) {
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to enum (limited to common values)
        DB::statement("ALTER TABLE landing_pages MODIFY COLUMN status ENUM('draft', 'active', 'expired', 'paused') DEFAULT 'draft'");
    }
};

