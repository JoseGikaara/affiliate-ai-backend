<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutRequestedNotification extends Notification implements ShouldQueue
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
        $isAdmin = $notifiable->is_admin ?? false;

        if ($isAdmin) {
            return (new MailMessage)
                ->subject('New Payout Request Received')
                ->line('A new payout request has been submitted by an affiliate.')
                ->line("Affiliate: {$this->payoutRequest->affiliate->name}")
                ->line("Amount: {$this->payoutRequest->currency} {$this->payoutRequest->amount}")
                ->line("Method: {$this->payoutRequest->payout_method}")
                ->action('View Request', url('/admin/payouts/requests/' . $this->payoutRequest->id))
                ->line('Please review and approve or reject this request.');
        } else {
            return (new MailMessage)
                ->subject('Payout Request Received')
                ->line('Your payout request has been received and is pending review.')
                ->line("Amount: {$this->payoutRequest->currency} {$this->payoutRequest->amount}")
                ->line("Method: {$this->payoutRequest->payout_method}")
                ->line('You will be notified once your request has been reviewed.')
                ->line('If you have any questions, please contact support.');
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isAdmin = $notifiable->is_admin ?? false;

        return [
            'type' => 'payout_requested',
            'payout_request_id' => $this->payoutRequest->id,
            'title' => $isAdmin 
                ? 'New Payout Request' 
                : 'Payout Request Received',
            'message' => $isAdmin
                ? "Affiliate {$this->payoutRequest->affiliate->name} requested a payout of {$this->payoutRequest->currency} {$this->payoutRequest->amount}"
                : "Your payout request of {$this->payoutRequest->currency} {$this->payoutRequest->amount} has been received",
            'amount' => $this->payoutRequest->amount,
            'currency' => $this->payoutRequest->currency,
            'method' => $this->payoutRequest->payout_method,
        ];
    }
}

