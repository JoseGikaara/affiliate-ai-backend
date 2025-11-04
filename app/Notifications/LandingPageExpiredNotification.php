<?php

namespace App\Notifications;

use App\Models\LandingPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LandingPageExpiredNotification extends Notification implements ShouldQueue
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
            ->subject('Landing Page Expired')
            ->line("Your landing page '{$this->landingPage->title}' has expired and is no longer active.")
            ->line("Subdomain: {$this->landingPage->subdomain}.affnet.app")
            ->line('To reactivate it, please renew it with credits.')
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
            'type' => 'landing_page_expired',
            'landing_page_id' => $this->landingPage->id,
            'title' => 'Landing Page Expired',
            'message' => "Your landing page '{$this->landingPage->title}' has expired and is no longer active. Renew it to reactivate.",
            'landing_page_title' => $this->landingPage->title,
            'subdomain' => $this->landingPage->subdomain,
        ];
    }
}
