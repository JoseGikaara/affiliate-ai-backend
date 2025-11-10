<?php

namespace App\Console\Commands;

use App\Models\Modules\Dropservicing\UserGig;
use App\Services\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RenewDropservicingGigs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dropservicing:renew-gigs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-renew active dropservicing gigs (monthly, deducts 50 credits per gig)';

    /**
     * Execute the console command.
     */
    public function handle(CreditService $creditService): int
    {
        $this->info('Starting dropservicing gig auto-renewal...');

        // Get all active gigs that need renewal (last renewed more than 30 days ago or never renewed)
        $gigs = UserGig::where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('last_renewed_at')
                    ->orWhere('last_renewed_at', '<', now()->subDays(30));
            })
            ->with(['user', 'service'])
            ->get();

        $renewed = 0;
        $failed = 0;
        $renewalCost = 50;

        foreach ($gigs as $gig) {
            $user = $gig->user;

            // Check if user has enough credits
            if (!$creditService->hasEnoughCredits($user, $renewalCost)) {
                $this->warn("Insufficient credits for gig #{$gig->id} (User: {$user->email}). Deactivating gig.");
                
                $gig->update(['status' => 'inactive']);
                
                // TODO: Send notification to user about deactivation
                // $user->notify(new GigDeactivatedNotification($gig));
                
                $failed++;
                continue;
            }

            // Deduct credits
            $creditService->deductCredits($user, $renewalCost, "Gig auto-renewal: {$gig->title}");

            // Update last_renewed_at
            $gig->update(['last_renewed_at' => now()]);

            $this->info("Renewed gig #{$gig->id}: {$gig->title}");

            // TODO: Send notification to user
            // $user->notify(new GigRenewedNotification($gig));

            $renewed++;
        }

        $this->info("Renewal complete: {$renewed} gigs renewed, {$failed} deactivated due to insufficient credits.");

        Log::info('Dropservicing gig auto-renewal completed', [
            'renewed' => $renewed,
            'failed' => $failed,
        ]);

        return Command::SUCCESS;
    }
}
