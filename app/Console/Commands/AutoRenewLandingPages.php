<?php

namespace App\Console\Commands;

use App\Models\BillingLog;
use App\Models\LandingPage;
use App\Models\User;
use App\Notifications\LandingPageRenewalFailedNotification;
use App\Notifications\LandingPageRenewalSuccessNotification;
use App\Services\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoRenewLandingPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landing-pages:auto-renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-renew landing pages for users with sufficient credits';

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
        $this->info('Starting auto-renewal process...');

        // Get all active landing pages that are due for renewal
        $dueForRenewal = LandingPage::where('status', 'active')
            ->where('auto_renew', true)
            ->whereNotNull('next_renewal_date')
            ->where('next_renewal_date', '<=', now())
            ->with('user')
            ->get();

        if ($dueForRenewal->isEmpty()) {
            $this->info('No landing pages due for renewal.');
            return Command::SUCCESS;
        }

        $this->info("Found {$dueForRenewal->count()} landing page(s) due for renewal.");

        $successCount = 0;
        $failedCount = 0;

        foreach ($dueForRenewal as $landingPage) {
            try {
                $user = $landingPage->user;
                $renewalCredits = $landingPage->renewal_credits ?? $landingPage->credit_cost;

                // Check if user has enough credits
                if (!$this->creditService->hasEnoughCredits($user, $renewalCredits)) {
                    // Deactivate the page and log failure
                    DB::transaction(function () use ($landingPage, $user, $renewalCredits) {
                        $landingPage->update([
                            'status' => 'expired',
                            'auto_renew' => false,
                        ]);

                        // Create billing log for failure
                        BillingLog::create([
                            'user_id' => $user->id,
                            'landing_page_id' => $landingPage->id,
                            'type' => 'auto_renew',
                            'credits_deducted' => 0,
                            'status' => 'failed',
                            'message' => "Auto-renewal failed: Insufficient credits. Required: {$renewalCredits}, Available: {$user->credits}",
                        ]);

                        // Notify user
                        $user->notify(new LandingPageRenewalFailedNotification(
                            $landingPage,
                            $renewalCredits,
                            $user->credits
                        ));
                    });

                    $this->warn("Failed to renew landing page #{$landingPage->id} - insufficient credits.");
                    $failedCount++;
                    continue;
                }

                // Renew the page
                $renewalInterval = config('landing_pages.renewal.default_interval', 30);
                DB::transaction(function () use ($landingPage, $user, $renewalCredits, $renewalInterval) {
                    // Deduct renewal credits
                    $this->creditService->deductCredits(
                        $user,
                        $renewalCredits,
                        "Auto-renewal for landing page: {$landingPage->title}"
                    );

                    // Update renewal dates
                    $now = now();
                    $expiresAt = $now->copy()->addDays($renewalInterval);
                    $landingPage->update([
                        'expires_at' => $expiresAt,
                        'next_renewal_date' => $expiresAt,
                        'last_renewal_date' => $now,
                        'credits_used' => ($landingPage->credits_used ?? 0) + $renewalCredits,
                    ]);

                    // Create billing log for success
                    BillingLog::create([
                        'user_id' => $user->id,
                        'landing_page_id' => $landingPage->id,
                        'type' => 'auto_renew',
                        'credits_deducted' => $renewalCredits,
                        'status' => 'success',
                        'message' => "Auto-renewal successful. Extended by {$renewalInterval} days.",
                    ]);

                    // Notify user
                    $user->notify(new LandingPageRenewalSuccessNotification($landingPage));
                });

                $this->info("Successfully renewed landing page #{$landingPage->id} for user {$user->name}.");
                $successCount++;
            } catch (\Exception $e) {
                Log::error('Failed to auto-renew landing page', [
                    'landing_page_id' => $landingPage->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Error renewing landing page #{$landingPage->id}: {$e->getMessage()}");
                $failedCount++;
            }
        }

        $this->info("Auto-renewal completed: {$successCount} succeeded, {$failedCount} failed.");

        return Command::SUCCESS;
    }
}
