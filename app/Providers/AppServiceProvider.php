<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return $frontendUrl . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        // Schedule daily reports generation
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('reports:daily')->dailyAt('01:00');
            
            // Schedule payout reports (daily at midnight)
            $schedule->command('payouts:generate-daily-reports')
                ->dailyAt(config('payouts.reports.schedule_time', '00:00'));
            
            // Schedule pending payout reminders (daily at 10:00 AM)
            $schedule->command('payouts:remind-pending')
                ->dailyAt('10:00');
            
            // Schedule landing pages expiration check (daily at 02:00 AM)
            $schedule->command('landing-pages:expire')
                ->dailyAt('02:00');
            
            // Schedule landing pages auto-renewal (daily at 03:00 AM)
            $schedule->command('landing-pages:auto-renew')
                ->dailyAt('03:00');
            
            // Schedule auto-renewal job (runs every hour to check for due renewals)
            $schedule->job(new \App\Jobs\ProcessAutoRenewalJob())
                ->hourly();
            
            // Schedule dropservicing gig auto-renewal (monthly on the 1st at 4:00 AM)
            $schedule->command('dropservicing:renew-gigs')
                ->monthlyOn(1, '04:00');
        });
    }
}
