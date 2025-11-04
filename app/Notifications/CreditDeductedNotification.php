<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreditDeductedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $creditsDeducted,
        public int $remainingCredits,
        public string $description,
        public ?string $landingPageTitle = null
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
        $message = (new MailMessage)
            ->subject('Credits Deducted')
            ->line("{$this->creditsDeducted} credits have been deducted from your account.")
            ->line("Reason: {$this->description}");
        
        if ($this->landingPageTitle) {
            $message->line("Landing Page: {$this->landingPageTitle}");
        }
        
        return $message
            ->line("Remaining balance: {$this->remainingCredits} credits")
            ->action('View Billing History', config('app.frontend_url') . '/dashboard/credits')
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
            'type' => 'credit_deducted',
            'title' => 'Credits Deducted',
            'message' => "{$this->creditsDeducted} credits deducted: {$this->description}. Remaining: {$this->remainingCredits} credits.",
            'credits_deducted' => $this->creditsDeducted,
            'remaining_credits' => $this->remainingCredits,
            'description' => $this->description,
            'landing_page_title' => $this->landingPageTitle,
        ];
    }
}
