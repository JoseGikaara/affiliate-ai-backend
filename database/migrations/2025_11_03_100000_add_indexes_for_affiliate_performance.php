<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for affiliate_links table
        $this->safeAddIndex('affiliate_links', 'affiliate_id');
        $this->safeAddIndex('affiliate_links', 'offer_id');
        $this->safeAddIndex('affiliate_links', 'tracking_id');

        // Add indexes for affiliates table
        $this->safeAddIndex('affiliates', 'user_id');
        $this->safeAddIndex('affiliates', 'status');
        $this->safeAddIndex('affiliates', 'referral_id');

        // Add indexes for conversions table
        if (Schema::hasTable('conversions')) {
            $this->safeAddIndex('conversions', 'affiliate_id');
            $this->safeAddIndex('conversions', 'offer_id');
            $this->safeAddIndex('conversions', 'affiliate_link_id');
            $this->safeAddIndex('conversions', 'status');
            $this->safeAddIndex('conversions', 'created_at');
        }

        // Add indexes for commissions table
        if (Schema::hasTable('commissions')) {
            $this->safeAddIndex('commissions', 'affiliate_id');
            $this->safeAddIndex('commissions', 'offer_id');
            $this->safeAddIndex('commissions', 'status');
            $this->safeAddIndex('commissions', 'date');
        }

        // Add indexes for offers table
        if (Schema::hasTable('offers')) {
            $this->safeAddIndex('offers', 'status');
        }
    }

    /**
     * Safely add an index if it doesn't exist
     */
    private function safeAddIndex(string $table, string $column): void
    {
        try {
            if (!$this->hasIndex($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->index($column);
                });
            }
        } catch (\Exception $e) {
            // Index might already exist, ignore the error
            // This handles cases where the index was created by another migration
            // or already exists in the database
            if (strpos($e->getMessage(), 'Duplicate key name') === false && 
                strpos($e->getMessage(), 'already exists') === false) {
                // Re-throw if it's a different error
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_links', function (Blueprint $table) {
            $table->dropIndex(['affiliate_id']);
            $table->dropIndex(['offer_id']);
            $table->dropIndex(['tracking_id']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['referral_id']);
        });

        if (Schema::hasTable('conversions')) {
            Schema::table('conversions', function (Blueprint $table) {
                $table->dropIndex(['affiliate_id']);
                $table->dropIndex(['offer_id']);
                $table->dropIndex(['affiliate_link_id']);
                $table->dropIndex(['status']);
                $table->dropIndex(['created_at']);
            });
        }

        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->dropIndex(['affiliate_id']);
                $table->dropIndex(['offer_id']);
                $table->dropIndex(['status']);
                $table->dropIndex(['date']);
            });
        }

        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                $table->dropIndex(['status']);
            });
        }
    }

    /**
     * Check if an index exists for a column
     */
    private function hasIndex(string $table, string $column): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        
        // Generate expected index name (Laravel convention: table_column_index)
        $indexName = "{$table}_{$column}_index";
        
        if ($driver === 'sqlite') {
            // For SQLite, check if index exists by querying sqlite_master
            try {
                $result = $connection->select(
                    "SELECT name FROM sqlite_master 
                     WHERE type='index' AND name = ?",
                    [$indexName]
                );
                return count($result) > 0;
            } catch (\Exception $e) {
                return false;
            }
        } elseif ($driver === 'mysql') {
            // For MySQL, use information_schema
            try {
                $databaseName = $connection->getDatabaseName();
                $result = $connection->select(
                    "SELECT COUNT(*) as count FROM information_schema.statistics 
                     WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                    [$databaseName, $table, $indexName]
                );
                return $result[0]->count > 0;
            } catch (\Exception $e) {
                return false;
            }
        } else {
            // For PostgreSQL or other databases
            try {
                $databaseName = $connection->getDatabaseName();
                $result = $connection->select(
                    "SELECT COUNT(*) as count FROM pg_indexes 
                     WHERE schemaname = ? AND tablename = ? AND indexname = ?",
                    ['public', $table, $indexName]
                );
                return $result[0]->count > 0;
            } catch (\Exception $e) {
                return false;
            }
        }
    }
};

