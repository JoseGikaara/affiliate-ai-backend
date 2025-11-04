<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update enum to: active, paused, expired, pending
        // Map old statuses to new ones:
        // 'draft' -> 'pending'
        // 'inactive' -> 'paused'
        // 'active' -> 'active' (unchanged)
        // 'expired' -> 'expired' (unchanged)
        
        // First, update existing data
        DB::table('landing_pages')
            ->where('status', 'draft')
            ->update(['status' => 'pending']);
        
        DB::table('landing_pages')
            ->where('status', 'inactive')
            ->update(['status' => 'paused']);
        
        // Then modify the enum (only for MySQL/MariaDB, SQLite doesn't support MODIFY COLUMN)
        $driver = DB::getDriverName();
        
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE landing_pages MODIFY COLUMN status ENUM('active', 'paused', 'expired', 'pending') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Map back to old statuses
        DB::table('landing_pages')
            ->where('status', 'pending')
            ->update(['status' => 'draft']);
        
        DB::table('landing_pages')
            ->where('status', 'paused')
            ->update(['status' => 'inactive']);
        
        $driver = DB::getDriverName();
        
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE landing_pages MODIFY COLUMN status ENUM('draft', 'active', 'expired', 'inactive') DEFAULT 'draft'");
        }
    }
};

