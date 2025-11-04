<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowCreditsWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $requiredCredits,
        public int $currentCredits,
        public string $landingPageTitle
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
            ->subject('Low Credits Warning')
            ->line("You don't have enough credits to renew your landing page '{$this->landingPageTitle}'.")
            ->line("Required: {$this->requiredCredits} credits")
            ->line("Current balance: {$this->currentCredits} credits")
            ->line('Please top up your account to continue hosting your landing pages.')
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
            'type' => 'low_credits_warning',
            'title' => 'Low Credits Warning',
            'message' => "You don't have enough credits ({$this->currentCredits}) to renew '{$this->landingPageTitle}'. Required: {$this->requiredCredits} credits.",
            'required_credits' => $this->requiredCredits,
            'current_credits' => $this->currentCredits,
            'landing_page_title' => $this->landingPageTitle,
        ];
    }
}
