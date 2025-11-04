<?php

namespace App\Console\Commands;

use App\Models\LandingPage;
use App\Notifications\LandingPageExpiredNotification;
use App\Notifications\LandingPageExpiringNotification;
use App\Notifications\LandingPageRenewalUpcomingNotification;
use App\Services\LandingPageService;
use Illuminate\Console\Command;

class ExpireLandingPages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'landing-pages:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unpublish expired landing pages and send expiry warnings';

    public function __construct(
        protected LandingPageService $landingPageService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing expired landing pages...');

        // Unpublish expired pages
        $expiredPages = LandingPage::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        $expiredCount = 0;
        foreach ($expiredPages as $page) {
            try {
                // Undeploy the landing page
                $this->landingPageService->undeploy($page);

                // Update status
                $page->update([
                    'status' => 'expired',
                ]);

                // Send expired notification
                $page->user->notify(new LandingPageExpiredNotification($page));

                $expiredCount++;
                $this->info("Expired landing page: {$page->title} (ID: {$page->id})");
            } catch (\Exception $e) {
                $this->error("Failed to expire landing page {$page->id}: {$e->getMessage()}");
            }
        }

        // Send expiry warnings (3 days before expiry)
        $expiringSoonPages = LandingPage::where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(3))
            ->with('user')
            ->get()
            ->filter(function ($page) {
                // Check if user already received notification today
                $todayNotifications = $page->user->notifications()
                    ->where('type', 'App\\Notifications\\LandingPageExpiringNotification')
                    ->whereDate('created_at', today())
                    ->count();
                return $todayNotifications === 0;
            });

        $warnedCount = 0;
        foreach ($expiringSoonPages as $page) {
            try {
                $page->user->notify(new LandingPageExpiringNotification($page));
                $warnedCount++;
                $this->info("Sent expiry warning for: {$page->title} (ID: {$page->id})");
            } catch (\Exception $e) {
                $this->error("Failed to send expiry warning for landing page {$page->id}: {$e->getMessage()}");
            }
        }

        // Send auto-renewal upcoming warnings (3 days before renewal)
        $renewalUpcomingPages = LandingPage::where('status', 'active')
            ->where('auto_renew', true)
            ->where('next_renewal_date', '>', now())
            ->where('next_renewal_date', '<=', now()->addDays(3))
            ->with('user')
            ->get()
            ->filter(function ($page) {
                // Check if user already received notification today
                $todayNotifications = $page->user->notifications()
                    ->where('type', 'App\\Notifications\\LandingPageRenewalUpcomingNotification')
                    ->whereDate('created_at', today())
                    ->count();
                return $todayNotifications === 0;
            });

        $renewalWarnedCount = 0;
        foreach ($renewalUpcomingPages as $page) {
            try {
                $daysUntilRenewal = $page->next_renewal_date->diffInDays(now());
                $page->user->notify(new LandingPageRenewalUpcomingNotification($page, $daysUntilRenewal));
                $renewalWarnedCount++;
                $this->info("Sent renewal upcoming warning for: {$page->title} (ID: {$page->id})");
            } catch (\Exception $e) {
                $this->error("Failed to send renewal warning for landing page {$page->id}: {$e->getMessage()}");
            }
        }

        $this->info("Completed! Expired: {$expiredCount}, Expiry warnings: {$warnedCount}, Renewal warnings: {$renewalWarnedCount}");
        
        return Command::SUCCESS;
    }
}
