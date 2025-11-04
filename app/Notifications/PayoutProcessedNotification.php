<?php

namespace App\Notifications;

use App\Models\Payout;
use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutProcessedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PayoutRequest $payoutRequest,
        public Payout $payout
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
            ->subject('Payout Processed Successfully')
            ->line('Your payout has been processed and completed successfully.')
            ->line("Amount: {$this->payoutRequest->currency} {$this->payout->net_amount}")
            ->line("Method: {$this->payoutRequest->payout_method}");

        if ($this->payout->external_payout_id) {
            $message->line("Transaction ID: {$this->payout->external_payout_id}");
        }

        if ($this->payout->fee > 0) {
            $message->line("Processing Fee: {$this->payoutRequest->currency} {$this->payout->fee}");
        }

        $message->action('View Details', url('/affiliate/payouts'))
            ->line('Thank you for using our service!');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payout_processed',
            'payout_request_id' => $this->payoutRequest->id,
            'payout_id' => $this->payout->id,
            'title' => 'Payout Processed',
            'message' => "Your payout of {$this->payoutRequest->currency} {$this->payout->net_amount} has been processed successfully",
            'amount' => $this->payout->net_amount,
            'currency' => $this->payoutRequest->currency,
            'external_id' => $this->payout->external_payout_id,
        ];
    }
}

