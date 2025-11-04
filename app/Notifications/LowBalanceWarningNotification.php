<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowBalanceWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $currentBalance,
        public int $threshold
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
            ->subject('Low Credit Balance Warning')
            ->line("Your credit balance is low: {$this->currentBalance} credits remaining.")
            ->line("Threshold: {$this->threshold} credits")
            ->line('Top up your account now to ensure your landing pages continue to renew automatically.')
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
            'type' => 'low_balance_warning',
            'title' => 'Low Credit Balance Warning',
            'message' => "Your credit balance is low: {$this->currentBalance} credits remaining. Top up now to keep your landing pages active.",
            'current_balance' => $this->currentBalance,
            'threshold' => $this->threshold,
        ];
    }
}
