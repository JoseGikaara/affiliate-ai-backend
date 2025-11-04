<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PayPalService
{
    protected $clientId;
    protected $secret;
    protected $env;
    protected $baseUrl;

    public function __construct()
    {
        $this->clientId = config('payouts.paypal.client_id');
        $this->secret = config('payouts.paypal.secret');
        $this->env = config('payouts.paypal.env', 'sandbox');
        $this->baseUrl = $this->env === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
    }

    /**
     * Get PayPal OAuth access token
     */
    protected function getAccessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->secret)
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if (!$response->successful()) {
            Log::error('PayPal OAuth failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new Exception('Failed to obtain PayPal access token: ' . $response->body());
        }

        $data = $response->json();
        return $data['access_token'];
    }

    /**
     * Create a payout batch
     * 
     * @param array $items Array of payout items with: email, amount, currency
     * @return array PayPal response with batch_id and status
     */
    public function createPayout(array $items): array
    {
        if (empty($this->clientId) || empty($this->secret)) {
            throw new Exception('PayPal credentials not configured');
        }

        $accessToken = $this->getAccessToken();

        // Format items for PayPal
        $payoutItems = array_map(function ($item) {
            return [
                'recipient_type' => 'EMAIL',
                'amount' => [
                    'value' => number_format($item['amount'], 2, '.', ''),
                    'currency' => $item['currency'] ?? 'USD',
                ],
                'receiver' => $item['email'],
                'note' => $item['note'] ?? 'Affiliate payout',
                'sender_item_id' => $item['sender_item_id'] ?? uniqid('payout_'),
            ];
        }, $items);

        $payload = [
            'sender_batch_header' => [
                'sender_batch_id' => 'batch_' . time() . '_' . uniqid(),
                'email_subject' => 'You have a payout',
                'email_message' => 'You have received a payout. Thanks for using our service!',
            ],
            'items' => $payoutItems,
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$accessToken}",
        ])->post("{$this->baseUrl}/v1/payments/payouts", $payload);

        if (!$response->successful()) {
            Log::error('PayPal payout creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);
            throw new Exception('PayPal payout creation failed: ' . $response->body());
        }

        $data = $response->json();
        
        Log::info('PayPal payout created', [
            'batch_id' => $data['batch_header']['payout_batch_id'] ?? null,
            'status' => $data['batch_header']['batch_status'] ?? null,
        ]);

        return [
            'batch_id' => $data['batch_header']['payout_batch_id'],
            'status' => $data['batch_header']['batch_status'],
            'response' => $data,
        ];
    }

    /**
     * Get payout batch status
     */
    public function getPayoutStatus(string $batchId): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
        ])->get("{$this->baseUrl}/v1/payments/payouts/{$batchId}");

        if (!$response->successful()) {
            throw new Exception('Failed to get PayPal payout status: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Calculate PayPal fees
     */
    public function calculateFee(float $amount): array
    {
        $feePercentage = config('payouts.paypal.fee_percentage', 2.9);
        $feeFixed = config('payouts.paypal.fee_fixed', 0.30);

        $fee = ($amount * ($feePercentage / 100)) + $feeFixed;
        $netAmount = $amount - $fee;

        return [
            'fee' => round($fee, 2),
            'net_amount' => round($netAmount, 2),
        ];
    }
}

