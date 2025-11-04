<?php

namespace App\Jobs;

use App\Models\Payout;
use App\Models\PayoutRequest;
use App\Models\Affiliate;
use App\Services\PayPalService;
use App\Services\MpesaService;
use App\Notifications\PayoutProcessedNotification;
use App\Notifications\PayoutFailedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Exception;

class ProcessPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PayoutRequest $payoutRequest,
        public Payout $payout
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->payout->update([
            'attempted_at' => now(),
            'status' => 'processing',
        ]);

        try {
            $provider = $this->payout->payout_provider;
            $automated = config('payouts.automated', false);

            // If automation is disabled or provider is manual, mark as pending manual
            if (!$automated || $provider === 'manual') {
                $this->payout->update([
                    'status' => 'processing', // Keep processing, but notify admin
                    'metadata' => array_merge($this->payout->metadata ?? [], [
                        'requires_manual_processing' => true,
                        'reason' => !$automated ? 'Automation disabled' : 'Manual payout method',
                    ]),
                ]);

                // Notify admin that manual processing is required
                Log::info('Payout requires manual processing', [
                    'payout_id' => $this->payout->id,
                    'payout_request_id' => $this->payoutRequest->id,
                ]);

                // Mark payout request as pending_manual (we'll update the status)
                $this->payoutRequest->update([
                    'status' => 'processing',
                ]);

                return;
            }

            // Process automated payout
            if ($provider === 'paypal') {
                $this->processPayPalPayout();
            } elseif ($provider === 'mpesa') {
                $this->processMpesaPayout();
            } else {
                throw new Exception("Unknown payout provider: {$provider}");
            }
        } catch (Exception $e) {
            Log::error('Payout processing failed', [
                'payout_id' => $this->payout->id,
                'payout_request_id' => $this->payoutRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->payout->update([
                'status' => 'failed',
                'metadata' => array_merge($this->payout->metadata ?? [], [
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ]),
            ]);

            $this->payoutRequest->update([
                'status' => 'failed',
            ]);

            // Notify affiliate and admin of failure
            $this->payout->affiliate->user->notify(
                new PayoutFailedNotification($this->payoutRequest, $this->payout, $e->getMessage())
            );

            // Notify admins
            $adminEmails = config('payouts.notifications.admin_emails', []);
            if (!empty($adminEmails)) {
                Notification::route('mail', $adminEmails)
                    ->notify(new \App\Notifications\PayoutFailedNotification(
                        $this->payoutRequest,
                        $this->payout,
                        $e->getMessage()
                    ));
            }
        }
    }

    /**
     * Process PayPal payout
     */
    protected function processPayPalPayout(): void
    {
        $affiliate = $this->payout->affiliate;
        $accountDetails = $this->payoutRequest->account_details ?? [];
        $email = $accountDetails['email'] ?? $affiliate->payout_email ?? $affiliate->email;

        if (empty($email)) {
            throw new Exception('PayPal email not provided in account details');
        }

        $paypalService = new PayPalService();
        
        // Create payout via PayPal
        $response = $paypalService->createPayout([
            [
                'email' => $email,
                'amount' => (float) $this->payout->net_amount,
                'currency' => $this->payoutRequest->currency ?? 'USD',
                'note' => 'Affiliate payout',
                'sender_item_id' => 'payout_' . $this->payout->id,
            ],
        ]);

        // Update payout with PayPal response
        $this->payout->update([
            'external_payout_id' => $response['batch_id'],
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => array_merge($this->payout->metadata ?? [], [
                'paypal_response' => $response,
                'processed_at' => now()->toISOString(),
            ]),
        ]);

        // Update payout request
        $this->payoutRequest->update([
            'status' => 'completed',
            'external_txn_id' => $response['batch_id'],
        ]);

        // Deduct from affiliate balance
        $affiliate->decrement('commission_earned', $this->payoutRequest->amount);

        // Notify affiliate
        $affiliate->user->notify(
            new PayoutProcessedNotification($this->payoutRequest, $this->payout)
        );

        Log::info('Payout processed successfully', [
            'payout_id' => $this->payout->id,
            'batch_id' => $response['batch_id'],
        ]);
    }

    /**
     * Process M-Pesa payout (stub - throws exception)
     */
    protected function processMpesaPayout(): void
    {
        $mpesaService = new MpesaService();
        
        // This will throw an exception as it's not implemented
        $accountDetails = $this->payoutRequest->account_details ?? [];
        $phoneNumber = $accountDetails['phone'] ?? $this->payout->affiliate->payout_phone;

        if (empty($phoneNumber)) {
            throw new Exception('M-Pesa phone number not provided');
        }

        // Attempt to create payout (will fail with not implemented)
        $response = $mpesaService->createPayout([
            'phone_number' => $phoneNumber,
            'amount' => (float) $this->payout->net_amount,
            'remarks' => 'Affiliate payout',
        ]);

        // If we ever get here, update payout
        $this->payout->update([
            'external_payout_id' => $response['transaction_id'] ?? null,
            'status' => 'completed',
            'completed_at' => now(),
            'metadata' => array_merge($this->payout->metadata ?? [], [
                'mpesa_response' => $response,
            ]),
        ]);
    }
}

