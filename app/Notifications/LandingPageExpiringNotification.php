<?php

namespace App\Notifications;

use App\Models\LandingPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LandingPageExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LandingPage $landingPage
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $daysUntilExpiry = $this->landingPage->expires_at->diffInDays(now());
        
        return (new MailMessage)
            ->subject('Your Landing Page is Expiring Soon')
            ->line("Your landing page '{$this->landingPage->title}' is expiring in {$daysUntilExpiry} days.")
            ->line("Subdomain: {$this->landingPage->subdomain}")
            ->line('To keep it active, please renew it before it expires.')
            ->action('Renew Landing Page', config('app.frontend_url') . '/dashboard/landing-pages')
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $daysUntilExpiry = $this->landingPage->expires_at->diffInDays(now());
        
        return [
            'type' => 'landing_page_expiring',
            'landing_page_id' => $this->landingPage->id,
            'title' => 'Landing Page Expiring Soon',
            'message' => "Your landing page '{$this->landingPage->title}' is expiring in {$daysUntilExpiry} days. Renew it to keep it active.",
            'landing_page_title' => $this->landingPage->title,
            'subdomain' => $this->landingPage->subdomain,
            'expires_at' => $this->landingPage->expires_at->toISOString(),
            'days_until_expiry' => $daysUntilExpiry,
        ];
    }
}
