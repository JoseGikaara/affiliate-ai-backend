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
        Schema::table('payout_requests', function (Blueprint $table) {
            // Add currency column if it doesn't exist
            if (!Schema::hasColumn('payout_requests', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('amount');
            }
            
            // Handle payout_method/payment_method column
            if (Schema::hasColumn('payout_requests', 'payment_method') && !Schema::hasColumn('payout_requests', 'payout_method')) {
                // Rename payment_method to payout_method if it exists
                $table->renameColumn('payment_method', 'payout_method');
            } elseif (!Schema::hasColumn('payout_requests', 'payout_method')) {
                // Add payout_method if neither exists
                $table->string('payout_method')->default('paypal')->after('amount');
            } else {
                // Column exists, just ensure default
                $table->string('payout_method')->default('paypal')->change();
            }
            
            // Add account_details if it doesn't exist
            if (!Schema::hasColumn('payout_requests', 'account_details')) {
                $table->json('account_details')->nullable()->after('payout_method');
            }
            
            // Update status column if it exists
            if (Schema::hasColumn('payout_requests', 'status')) {
                $table->string('status')->default('pending')->change();
            }
            
            // Add admin_notes if it doesn't exist
            if (!Schema::hasColumn('payout_requests', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
                if (Schema::hasColumn('payout_requests', 'notes')) {
                    $table->text('admin_notes')->nullable()->after('notes')->change();
                }
            }
            
            // Add external_txn_id if it doesn't exist
            if (!Schema::hasColumn('payout_requests', 'external_txn_id')) {
                $table->string('external_txn_id')->nullable();
                if (Schema::hasColumn('payout_requests', 'admin_notes')) {
                    $table->string('external_txn_id')->nullable()->after('admin_notes')->change();
                }
            }
            
            // Add processed_by if it doesn't exist
            if (!Schema::hasColumn('payout_requests', 'processed_by')) {
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                if (Schema::hasColumn('payout_requests', 'external_txn_id')) {
                    $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete()->after('external_txn_id')->change();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropColumn(['currency', 'account_details', 'admin_notes', 'external_txn_id', 'processed_by']);
            $table->string('payment_method')->default('paypal')->change();
            $table->text('notes')->nullable();
        });
    }
};

