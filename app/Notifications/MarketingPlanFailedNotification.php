<?php

namespace App\Notifications;

use App\Models\DropservicingMarketingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketingPlanFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DropservicingMarketingPlan $plan,
        public string $errorMessage
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
        $planType = ucfirst(str_replace('-', ' ', $this->plan->plan_type));

        return (new MailMessage)
            ->subject('Marketing Plan Generation Failed')
            ->line("We encountered an issue generating your {$planType} marketing plan.")
            ->line("Error: {$this->errorMessage}")
            ->line('Your credits have been deducted, but you can regenerate the plan at no additional cost.')
            ->action('View Plans', config('app.frontend_url') . '/dashboard/dropservicing/marketing-plans')
            ->line('If this issue persists, please contact support.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $planType = ucfirst(str_replace('-', ' ', $this->plan->plan_type));
        
        return [
            'type' => 'marketing_plan_failed',
            'plan_id' => $this->plan->id,
            'title' => 'Marketing Plan Generation Failed',
            'message' => "Your {$planType} marketing plan generation failed. Error: {$this->errorMessage}",
            'plan_type' => $this->plan->plan_type,
            'error_message' => $this->errorMessage,
        ];
    }
}

