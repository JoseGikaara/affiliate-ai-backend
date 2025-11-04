<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PayoutRequest $payoutRequest
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
            ->subject('Payout Request Approved')
            ->line('Great news! Your payout request has been approved.')
            ->line("Amount: {$this->payoutRequest->currency} {$this->payoutRequest->amount}")
            ->line("Method: {$this->payoutRequest->payout_method}")
            ->line('Your payout is now being processed. You will be notified once it is completed.')
            ->action('View Status', url('/affiliate/payouts'))
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
            'type' => 'payout_approved',
            'payout_request_id' => $this->payoutRequest->id,
            'title' => 'Payout Request Approved',
            'message' => "Your payout request of {$this->payoutRequest->currency} {$this->payoutRequest->amount} has been approved and is being processed",
            'amount' => $this->payoutRequest->amount,
            'currency' => $this->payoutRequest->currency,
            'method' => $this->payoutRequest->payout_method,
        ];
    }
}

