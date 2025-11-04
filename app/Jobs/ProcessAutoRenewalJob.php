<?php

namespace App\Jobs;

use App\Models\LandingPage;
use App\Models\BillingLog;
use App\Services\CreditService;
use App\Notifications\LandingPageRenewalFailedNotification;
use App\Notifications\LandingPageRenewalSuccessNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAutoRenewalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CreditService $creditService): void
    {
        Log::info('Processing auto-renewal for landing pages...');

        // Get all active landing pages that are due for renewal
        $dueForRenewal = LandingPage::where('status', 'active')
            ->where('auto_renew', true)
            ->whereNotNull('next_renewal_date')
            ->where('next_renewal_date', '<=', now())
            ->with('user')
            ->get();

        if ($dueForRenewal->isEmpty()) {
            Log::info('No landing pages due for renewal.');
            return;
        }

        Log::info("Found {$dueForRenewal->count()} landing page(s) due for renewal.");

        $successCount = 0;
        $failedCount = 0;

        foreach ($dueForRenewal as $landingPage) {
            try {
                $user = $landingPage->user;
                $renewalCredits = $landingPage->renewal_credits ?? $landingPage->credit_cost;

                // Check if user has enough credits
                if (!$creditService->hasEnoughCredits($user, $renewalCredits)) {
                    // Deactivate the page
                    DB::transaction(function () use ($landingPage, $user, $renewalCredits) {
                        $landingPage->update([
                            'status' => 'expired',
                            'auto_renew' => false,
                        ]);

                        // Create billing log for failure
                        BillingLog::create([
                            'user_id' => $user->id,
                            'landing_page_id' => $landingPage->id,
                            'type' => 'auto_renew_failure',
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

                    $failedCount++;
                    Log::warning("Failed to renew landing page #{$landingPage->id} - insufficient credits.");
                    continue;
                }

                // Renew the page
                DB::transaction(function () use ($landingPage, $user, $renewalCredits, $creditService) {
                    // Deduct renewal credits
                    $creditService->deductCredits(
                        $user,
                        $renewalCredits,
                        "Auto-renewal for landing page: {$landingPage->title}"
                    );

                    // Update renewal dates
                    $landingPage->update([
                        'expires_at' => now()->addDays(30),
                        'next_renewal_date' => now()->addDays(30),
                        'last_renewal_date' => now(),
                        'credits_used' => ($landingPage->credits_used ?? 0) + $renewalCredits,
                    ]);

                    // Create billing log
                    BillingLog::create([
                        'user_id' => $user->id,
                        'landing_page_id' => $landingPage->id,
                        'type' => 'auto_renew',
                        'credits_deducted' => $renewalCredits,
                        'status' => 'success',
                        'message' => "Auto-renewal successful. Extended by 30 days.",
                    ]);

                    // Notify user
                    $user->notify(new LandingPageRenewalSuccessNotification(
                        $landingPage->fresh(),
                        $renewalCredits,
                        $user->fresh()->credits
                    ));
                });

                $successCount++;
                Log::info("Successfully renewed landing page #{$landingPage->id} for user {$user->name}.");
            } catch (\Exception $e) {
                Log::error('Failed to auto-renew landing page', [
                    'landing_page_id' => $landingPage->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $failedCount++;
            }
        }

        Log::info("Auto-renewal completed: {$successCount} succeeded, {$failedCount} failed.");
    }
}

