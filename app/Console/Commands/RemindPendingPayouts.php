<?php

namespace App\Console\Commands;

use App\Models\PayoutRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PayoutRequestedNotification;

class RemindPendingPayouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:remind-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders for pending manual payouts older than configured days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('payouts.reminders.enabled', true)) {
            $this->info('Payout reminders are disabled');
            return Command::SUCCESS;
        }

        $daysOld = config('payouts.reminders.days_old', 7);
        $cutoffDate = now()->subDays($daysOld);

        $this->info("Looking for pending payouts older than {$daysOld} days...");

        // Get pending/approved payout requests that are older than cutoff
        $pendingPayouts = PayoutRequest::whereIn('status', ['pending', 'approved', 'processing'])
            ->where('created_at', '<=', $cutoffDate)
            ->with(['affiliate.user'])
            ->get();

        if ($pendingPayouts->isEmpty()) {
            $this->info('No pending payouts found that require reminders');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingPayouts->count()} pending payouts requiring reminders");

        foreach ($pendingPayouts as $payoutRequest) {
            // Send reminder to admin
            $admins = \App\Models\User::where('is_admin', true)->get();
            
            if ($admins->isNotEmpty()) {
                Notification::send($admins, new \App\Notifications\PayoutRequestedNotification($payoutRequest));
                
                Log::info('Reminder sent for pending payout', [
                    'payout_request_id' => $payoutRequest->id,
                    'age_days' => $payoutRequest->created_at->diffInDays(now()),
                ]);
            }
        }

        $this->info('Reminders sent successfully');
        return Command::SUCCESS;
    }
}

