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
        // For SQLite, enum is stored as string, so we just need to ensure data integrity
        // For MySQL/MariaDB, we modify the enum
        $driver = DB::getDriverName();
        
        if ($driver !== 'sqlite') {
            // MySQL/MariaDB: Modify enum
            DB::statement("ALTER TABLE landing_pages MODIFY COLUMN status ENUM('draft', 'active', 'expired', 'inactive') DEFAULT 'draft'");
        }
        // For SQLite, enum columns are stored as strings, so no modification needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite, no action needed
        // For MySQL/MariaDB, remove 'inactive' from enum
        $driver = DB::getDriverName();
        
        if ($driver !== 'sqlite') {
            DB::statement("ALTER TABLE landing_pages MODIFY COLUMN status ENUM('draft', 'active', 'expired') DEFAULT 'draft'");
        }
    }
};
