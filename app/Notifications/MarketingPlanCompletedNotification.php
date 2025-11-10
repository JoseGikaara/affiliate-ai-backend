<?php

namespace App\Notifications;

use App\Models\DropservicingMarketingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketingPlanCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DropservicingMarketingPlan $plan
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
        $gigTitle = $this->plan->gig?->title ?? 'Your service';

        return (new MailMessage)
            ->subject('Marketing Plan Completed')
            ->line("Your {$planType} marketing plan for '{$gigTitle}' has been generated successfully!")
            ->line('The plan includes ad copy, content calendar, keywords, and strategic recommendations.')
            ->action('View Marketing Plan', config('app.frontend_url') . '/dashboard/dropservicing/marketing-plans/' . $this->plan->id)
            ->line('Thank you for using our service!');
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
            'type' => 'marketing_plan_completed',
            'plan_id' => $this->plan->id,
            'title' => 'Marketing Plan Completed',
            'message' => "Your {$planType} marketing plan has been generated successfully!",
            'plan_type' => $this->plan->plan_type,
            'gig_title' => $this->plan->gig?->title,
        ];
    }
}

