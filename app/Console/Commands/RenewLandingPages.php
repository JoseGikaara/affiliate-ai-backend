<?php

namespace App\Console\Commands;

use App\Models\BillingLog;
use App\Models\LandingPage;
use App\Notifications\LandingPageRenewalFailedNotification;
use App\Notifications\LandingPageRenewalSuccessNotification;
use App\Services\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * @deprecated This command is deprecated. Use landing-pages:auto-renew instead.
 * This file is kept for reference only and will be removed in a future version.
 */
class RenewLandingPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landing-pages:renew {--deprecated : This command is deprecated}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'DEPRECATED: Auto-renew landing pages that are due for renewal. Use landing-pages:auto-renew instead.';

    public function __construct(
        protected CreditService $creditService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('DEPRECATED: This command is deprecated. Please use "landing-pages:auto-renew" instead.');
        $this->info('Processing landing page renewals...');

        $renewalInterval = config('landing_pages.renewal.default_interval', 30); // days
        
        // Fetch landing pages due for renewal
        $pagesDue = LandingPage::where('status', 'active')
            ->where('auto_renew', true)
            ->where('next_renewal_date', '<=', now())
            ->with('user')
            ->get();

        $renewedCount = 0;
        $failedCount = 0;

        foreach ($pagesDue as $page) {
            try {
                $user = $page->user;
                $creditCost = $page->credit_cost;

                // Check if user has enough credits
                if (!$this->creditService->hasEnoughCredits($user, $creditCost)) {
                    // Mark as expired
                    DB::transaction(function () use ($page, $user, $creditCost) {
                        $page->update([
                            'status' => 'expired',
                            'next_renewal_date' => null,
                        ]);

                        // Create billing log for failure
                        BillingLog::create([
                            'user_id' => $user->id,
                            'landing_page_id' => $page->id,
                            'type' => 'failure',
                            'credits_deducted' => 0,
                            'status' => 'failed',
                            'message' => "Auto-renewal failed: Insufficient credits. Required: {$creditCost}, Available: {$user->credits}",
                        ]);

                        // Send notification
                        $user->notify(new LandingPageRenewalFailedNotification(
                            $page,
                            $creditCost,
                            $user->credits
                        ));
                    });

                    $failedCount++;
                    $this->warn("Failed to renew: {$page->title} (ID: {$page->id}) - Insufficient credits");
                    continue;
                }

                // Process renewal
                DB::transaction(function () use ($page, $user, $creditCost, $renewalInterval) {
                    // Deduct credits
                    $this->creditService->deductCredits(
                        $user,
                        $creditCost,
                        "Auto-renewed landing page: {$page->title}"
                    );

                    // Update landing page
                    $now = now();
                    $page->update([
                        'expires_at' => $now->copy()->addDays($renewalInterval),
                        'next_renewal_date' => $now->copy()->addDays($renewalInterval),
                        'last_renewal_date' => $now,
                    ]);

                    // Create billing log
                    BillingLog::create([
                        'user_id' => $user->id,
                        'landing_page_id' => $page->id,
                        'type' => 'auto_renew',
                        'credits_deducted' => $creditCost,
                        'status' => 'success',
                        'message' => "Auto-renewal successful. Extended by {$renewalInterval} days.",
                    ]);

                    // Send notification
                    $user->notify(new LandingPageRenewalSuccessNotification(
                        $page->fresh(),
                        $creditCost,
                        $user->fresh()->credits
                    ));
                });

                $renewedCount++;
                $this->info("Renewed: {$page->title} (ID: {$page->id})");
            } catch (\Exception $e) {
                $failedCount++;
                $this->error("Failed to renew landing page {$page->id}: {$e->getMessage()}");
                
                // Log failure
                BillingLog::create([
                    'user_id' => $page->user_id,
                    'landing_page_id' => $page->id,
                    'type' => 'failure',
                    'credits_deducted' => 0,
                    'status' => 'failed',
                    'message' => "Auto-renewal error: {$e->getMessage()}",
                ]);
            }
        }

        $this->info("Completed! Renewed: {$renewedCount}, Failed: {$failedCount}");
        
        return Command::SUCCESS;
    }
}
