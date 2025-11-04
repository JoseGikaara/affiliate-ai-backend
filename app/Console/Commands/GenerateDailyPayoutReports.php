<?php

namespace App\Console\Commands;

use App\Models\PayoutRequest;
use App\Models\Payout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class GenerateDailyPayoutReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payouts:generate-daily-reports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily payout summary reports and send to admins';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('payouts.reports.enabled', true)) {
            $this->info('Daily payout reports are disabled');
            return Command::SUCCESS;
        }

        $this->info('Generating daily payout reports...');

        $yesterday = now()->subDay();

        // Get yesterday's payout requests
        $requests = PayoutRequest::whereDate('created_at', $yesterday->toDateString())
            ->get();

        // Get yesterday's completed payouts
        $payouts = Payout::whereDate('completed_at', $yesterday->toDateString())
            ->where('status', 'completed')
            ->get();

        // Calculate statistics
        $stats = [
            'date' => $yesterday->toDateString(),
            'total_requests' => $requests->count(),
            'pending' => $requests->where('status', 'pending')->count(),
            'approved' => $requests->where('status', 'approved')->count(),
            'completed' => $payouts->count(),
            'rejected' => $requests->where('status', 'rejected')->count(),
            'failed' => $payouts->where('status', 'failed')->count(),
            'total_amount_requested' => $requests->sum('amount'),
            'total_amount_paid' => $payouts->sum('net_amount'),
            'total_fees' => $payouts->sum('fee'),
            'by_method' => $requests->groupBy('payout_method')->map->count(),
        ];

        // Get top affiliates
        $topAffiliates = PayoutRequest::whereDate('created_at', $yesterday->toDateString())
            ->with('affiliate')
            ->get()
            ->groupBy('affiliate_id')
            ->map(function ($group) {
                return [
                    'affiliate' => $group->first()->affiliate->name,
                    'requests' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                ];
            })
            ->sortByDesc('total_amount')
            ->take(10)
            ->values();

        $report = [
            'statistics' => $stats,
            'top_affiliates' => $topAffiliates,
        ];

        // Log report
        Log::info('Daily Payout Report Generated', $report);

        // Send email if recipients configured
        $recipients = config('payouts.reports.recipients', []);
        if (!empty($recipients) && !empty(array_filter($recipients))) {
            try {
                Mail::send('emails.payout-daily-report', ['report' => $report], function ($message) use ($recipients, $yesterday) {
                    $message->to($recipients)
                        ->subject('Daily Payout Report - ' . $yesterday->format('Y-m-d'));
                });

                $this->info('Report sent to: ' . implode(', ', $recipients));
            } catch (\Exception $e) {
                Log::error('Failed to send payout report email', [
                    'error' => $e->getMessage(),
                ]);
                $this->error('Failed to send email: ' . $e->getMessage());
            }
        } else {
            $this->warn('No email recipients configured for payout reports');
        }

        $this->info('Daily payout report generated successfully');
        return Command::SUCCESS;
    }
}

