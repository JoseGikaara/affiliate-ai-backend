<?php

namespace App\Notifications;

use App\Models\LandingPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LandingPageRenewalUpcomingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LandingPage $landingPage,
        public int $daysUntilRenewal
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
        return (new MailMessage)
            ->subject('Auto-Renewal Upcoming: ' . $this->landingPage->title)
            ->line("Your landing page '{$this->landingPage->title}' will be auto-renewed in {$this->daysUntilRenewal} days.")
            ->line("Credits required: {$this->landingPage->credit_cost}")
            ->line("Current balance: {$notifiable->credits}")
            ->line("Please ensure you have enough credits for auto-renewal.")
            ->action('Top Up Credits', config('app.frontend_url') . '/dashboard/credits')
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'renewal_upcoming',
            'landing_page_id' => $this->landingPage->id,
            'title' => 'Auto-Renewal Upcoming',
            'message' => "Your landing page '{$this->landingPage->title}' will be auto-renewed in {$this->daysUntilRenewal} days. Required: {$this->landingPage->credit_cost} credits. Current: {$notifiable->credits} credits.",
            'landing_page_title' => $this->landingPage->title,
            'days_until_renewal' => $this->daysUntilRenewal,
            'credit_cost' => $this->landingPage->credit_cost,
            'current_credits' => $notifiable->credits,
        ];
    }
}
