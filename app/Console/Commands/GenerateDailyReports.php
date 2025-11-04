<?php

namespace App\Console\Commands;

use App\Models\Affiliate;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\DailyReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateDailyReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily reports for all affiliates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating daily reports...');

        $yesterday = now()->subDay()->startOfDay();
        $endOfDay = now()->subDay()->endOfDay();

        // Get all affiliates
        $affiliates = Affiliate::where('status', 'active')->get();

        foreach ($affiliates as $affiliate) {
            // Calculate stats for yesterday
            $clicks = DB::table('affiliate_links')
                ->where('affiliate_id', $affiliate->id)
                ->whereBetween('updated_at', [$yesterday, $endOfDay])
                ->sum('total_clicks');

            // Get conversions for yesterday
            $conversions = Conversion::where('affiliate_id', $affiliate->id)
                ->whereBetween('created_at', [$yesterday, $endOfDay])
                ->count();

            // Get earnings for yesterday
            $earnings = Commission::where('affiliate_id', $affiliate->id)
                ->where('status', 'approved')
                ->whereBetween('date', [$yesterday->toDateString(), $endOfDay->toDateString()])
                ->sum('amount');

            // Create or update daily report
            DailyReport::updateOrCreate(
                [
                    'affiliate_id' => $affiliate->id,
                    'report_date' => $yesterday->toDateString(),
                ],
                [
                    'total_clicks' => $clicks,
                    'total_conversions' => $conversions,
                    'total_earnings' => $earnings,
                    'summary' => [
                        'affiliate_name' => $affiliate->name,
                        'referral_id' => $affiliate->referral_id,
                        'generated_at' => now()->toISOString(),
                    ],
                ]
            );

            $this->info("Report generated for {$affiliate->name} ({$affiliate->referral_id})");
        }

        // Generate aggregate report (for admin/all affiliates)
        $totalClicks = DB::table('affiliate_links')
            ->whereBetween('updated_at', [$yesterday, $endOfDay])
            ->sum('total_clicks');

        $totalConversions = Conversion::whereBetween('created_at', [$yesterday, $endOfDay])
            ->count();

        $totalEarnings = Commission::where('status', 'approved')
            ->whereBetween('date', [$yesterday->toDateString(), $endOfDay->toDateString()])
            ->sum('amount');

        DailyReport::updateOrCreate(
            [
                'affiliate_id' => null,
                'report_date' => $yesterday->toDateString(),
            ],
            [
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
                'total_earnings' => $totalEarnings,
                'summary' => [
                    'type' => 'aggregate',
                    'generated_at' => now()->toISOString(),
                ],
            ]
        );

        $this->info('Daily reports generated successfully!');
        return Command::SUCCESS;
    }
}

