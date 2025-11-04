<?php

namespace App\Notifications;

use App\Models\Payout;
use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PayoutRequest $payoutRequest,
        public Payout $payout,
        public string $errorMessage
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $isAdmin = $notifiable->is_admin ?? false;
        
        return $isAdmin ? ['mail', 'database'] : ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $isAdmin = $notifiable->is_admin ?? false;

        if ($isAdmin) {
            return (new MailMessage)
                ->subject('Payout Processing Failed - Admin Alert')
                ->error()
                ->line('A payout processing has failed and requires your attention.')
                ->line("Payout Request ID: {$this->payoutRequest->id}")
                ->line("Affiliate: {$this->payoutRequest->affiliate->name}")
                ->line("Amount: {$this->payoutRequest->currency} {$this->payoutRequest->amount}")
                ->line("Error: {$this->errorMessage}")
                ->action('View Details', url('/admin/payouts/' . $this->payout->id))
                ->line('Please review and manually process if needed.');
        } else {
            return (new MailMessage)
                ->subject('Payout Processing Issue')
                ->error()
                ->line('We encountered an issue while processing your payout.')
                ->line("Amount: {$this->payoutRequest->currency} {$this->payoutRequest->amount}")
                ->line('Our team has been notified and will resolve this shortly.')
                ->line('You will receive another notification once the issue is resolved.')
                ->action('Contact Support', url('/support'))
                ->line('We apologize for any inconvenience.');
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
            'type' => 'payout_failed',
            'payout_request_id' => $this->payoutRequest->id,
            'payout_id' => $this->payout->id,
            'title' => $isAdmin ? 'Payout Processing Failed' : 'Payout Issue',
            'message' => $isAdmin
                ? "Payout {$this->payoutRequest->id} failed: {$this->errorMessage}"
                : "There was an issue processing your payout. Our team has been notified.",
            'error' => $this->errorMessage,
            'amount' => $this->payoutRequest->amount,
            'currency' => $this->payoutRequest->currency,
        ];
    }
}

