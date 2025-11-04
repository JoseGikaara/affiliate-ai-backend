<?php

namespace App\Notifications;

use App\Models\LandingPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LandingPageRenewalSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LandingPage $landingPage,
        public int $creditsDeducted,
        public int $remainingCredits
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
            ->subject('Landing Page Renewed Successfully')
            ->line("Great news! Your landing page '{$this->landingPage->title}' has been auto-renewed successfully.")
            ->line("Credits deducted: {$this->creditsDeducted}")
            ->line("Remaining balance: {$this->remainingCredits}")
            ->line("Next renewal: {$this->landingPage->next_renewal_date->format('M d, Y')}")
            ->action('View Landing Pages', config('app.frontend_url') . '/dashboard/landing-pages')
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
            'type' => 'renewal_success',
            'landing_page_id' => $this->landingPage->id,
            'title' => 'Landing Page Renewed Successfully',
            'message' => "Your landing page '{$this->landingPage->title}' has been auto-renewed. {$this->creditsDeducted} credits deducted. Remaining: {$this->remainingCredits} credits.",
            'landing_page_title' => $this->landingPage->title,
            'credits_deducted' => $this->creditsDeducted,
            'remaining_credits' => $this->remainingCredits,
            'next_renewal_date' => $this->landingPage->next_renewal_date?->toISOString(),
        ];
    }
}
