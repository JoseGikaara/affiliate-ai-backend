<?php

namespace App\Notifications;

use App\Models\LandingPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LandingPageRenewalFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LandingPage $landingPage,
        public int $requiredCredits,
        public int $currentCredits
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
            ->subject('Landing Page Expired: Insufficient Credits')
            ->line("Unfortunately, your landing page '{$this->landingPage->title}' has expired due to insufficient credits.")
            ->line("Required: {$this->requiredCredits} credits")
            ->line("Current balance: {$this->currentCredits} credits")
            ->line("The page has been deactivated. You can reactivate it manually after topping up your credits.")
            ->action('Top Up Credits', config('app.frontend_url') . '/dashboard/credits')
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
        return [
            'type' => 'renewal_failed',
            'landing_page_id' => $this->landingPage->id,
            'title' => 'Landing Page Expired: Insufficient Credits',
            'message' => "Your landing page '{$this->landingPage->title}' expired due to insufficient credits. Required: {$this->requiredCredits}, Current: {$this->currentCredits}.",
            'landing_page_title' => $this->landingPage->title,
            'required_credits' => $this->requiredCredits,
            'current_credits' => $this->currentCredits,
        ];
    }
}
