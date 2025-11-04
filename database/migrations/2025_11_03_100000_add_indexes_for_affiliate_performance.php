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
        // Add indexes for affiliate_links table
        Schema::table('affiliate_links', function (Blueprint $table) {
            if (!$this->hasIndex('affiliate_links', 'affiliate_links_affiliate_id_index')) {
                $table->index('affiliate_id');
            }
            if (!$this->hasIndex('affiliate_links', 'affiliate_links_offer_id_index')) {
                $table->index('offer_id');
            }
            if (!$this->hasIndex('affiliate_links', 'affiliate_links_tracking_id_index')) {
                $table->index('tracking_id');
            }
        });

        // Add indexes for affiliates table
        Schema::table('affiliates', function (Blueprint $table) {
            if (!$this->hasIndex('affiliates', 'affiliates_user_id_index')) {
                $table->index('user_id');
            }
            if (!$this->hasIndex('affiliates', 'affiliates_status_index')) {
                $table->index('status');
            }
            if (!$this->hasIndex('affiliates', 'affiliates_referral_id_index')) {
                $table->index('referral_id');
            }
        });

        // Add indexes for conversions table
        if (Schema::hasTable('conversions')) {
            Schema::table('conversions', function (Blueprint $table) {
                if (!$this->hasIndex('conversions', 'conversions_affiliate_id_index')) {
                    $table->index('affiliate_id');
                }
                if (!$this->hasIndex('conversions', 'conversions_offer_id_index')) {
                    $table->index('offer_id');
                }
                if (!$this->hasIndex('conversions', 'conversions_affiliate_link_id_index')) {
                    $table->index('affiliate_link_id');
                }
                if (!$this->hasIndex('conversions', 'conversions_status_index')) {
                    $table->index('status');
                }
                if (!$this->hasIndex('conversions', 'conversions_created_at_index')) {
                    $table->index('created_at');
                }
            });
        }

        // Add indexes for commissions table
        if (Schema::hasTable('commissions')) {
            Schema::table('commissions', function (Blueprint $table) {
                if (!$this->hasIndex('commissions', 'commissions_affiliate_id_index')) {
                    $table->index('affiliate_id');
                }
                if (!$this->hasIndex('commissions', 'commissions_offer_id_index')) {
                    $table->index('offer_id');
                }
                if (!$this->hasIndex('commissions', 'commissions_status_index')) {
                    $table->index('status');
                }
                if (!$this->hasIndex('commissions', 'commissions_date_index')) {
                    $table->index('date');
                }
            });
        }

        // Add indexes for offers table
        if (Schema::hasTable('offers')) {
            Schema::table('offers', function (Blueprint $table) {
                if (!$this->hasIndex('offers', 'offers_status_index')) {
                    $table->index('status');
                }
            });
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
     * Check if an index exists
     */
    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        if ($connection->getDriverName() === 'sqlite') {
            // SQLite doesn't support index checking the same way
            return false;
        }
        
        $indexes = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$databaseName, $table, $indexName]
        );
        
        return $indexes[0]->count > 0;
    }
};

