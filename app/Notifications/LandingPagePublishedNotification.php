<?php

namespace App\Notifications;

use App\Models\LandingPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LandingPagePublishedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('Landing Page Published Successfully')
            ->line("Great news! Your landing page '{$this->landingPage->title}' has been published successfully.")
            ->line("Subdomain: {$this->landingPage->subdomain}.affnet.app")
            ->line("It will remain active for 30 days. Renew it before expiry to keep it active.")
            ->action('View Landing Page', $this->landingPage->domain ? "https://{$this->landingPage->domain}" : "https://{$this->landingPage->subdomain}.affnet.app")
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
            'type' => 'landing_page_published',
            'landing_page_id' => $this->landingPage->id,
            'title' => 'Landing Page Published',
            'message' => "Your landing page '{$this->landingPage->title}' has been published successfully. It will remain active for 30 days.",
            'landing_page_title' => $this->landingPage->title,
            'subdomain' => $this->landingPage->subdomain,
            'expires_at' => $this->landingPage->expires_at?->toISOString(),
        ];
    }
}
