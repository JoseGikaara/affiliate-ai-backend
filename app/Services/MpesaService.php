<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

class MpesaService
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $shortcode;
    protected $env;

    public function __construct()
    {
        $this->consumerKey = config('payouts.mpesa.consumer_key');
        $this->consumerSecret = config('payouts.mpesa.consumer_secret');
        $this->shortcode = config('payouts.mpesa.shortcode');
        $this->env = config('payouts.mpesa.env', 'sandbox');
    }

    /**
     * Create a B2C (Business to Customer) payout via M-Pesa Daraja API
     * 
     * NOTE: This is a stub implementation. To fully implement:
     * 1. Get OAuth access token from https://sandbox.safaricom.co.ke/oauth/v1/generate
     * 2. Use the access token to call B2C API endpoint
     * 3. Handle callbacks and queue results
     * 
     * @param array $data Contains: phone_number, amount, remarks
     * @return array
     * @throws Exception
     */
    public function createPayout(array $data): array
    {
        // Stub implementation - returns not implemented error
        Log::warning('M-Pesa payout attempted but not implemented', $data);
        
        throw new Exception(
            'M-Pesa automated payouts are not yet implemented. ' .
            'Please use manual processing or implement Daraja B2C API integration. ' .
            'Refer to: https://developer.safaricom.co.ke/apis/m-pesa/b2c-api'
        );
    }

    /**
     * Get OAuth access token (stub)
     */
    protected function getAccessToken(): string
    {
        // Stub - implement OAuth flow with Safaricom
        throw new Exception('M-Pesa OAuth not implemented');
    }

    /**
     * Calculate M-Pesa fees (if any)
     */
    public function calculateFee(float $amount): array
    {
        $feePercentage = config('payouts.mpesa.fee_percentage', 0);
        $feeFixed = config('payouts.mpesa.fee_fixed', 0);

        $fee = ($amount * ($feePercentage / 100)) + $feeFixed;
        $netAmount = $amount - $fee;

        return [
            'fee' => round($fee, 2),
            'net_amount' => round($netAmount, 2),
        ];
    }

    /**
     * Validate phone number format (Kenya M-Pesa format)
     */
    public function validatePhoneNumber(string $phone): bool
    {
        // Remove spaces and + signs
        $phone = preg_replace('/[\s+]/', '', $phone);
        
        // Should be 12 digits starting with 254
        return preg_match('/^254\d{9}$/', $phone) === 1;
    }

    /**
     * Format phone number to M-Pesa format (254XXXXXXXXX)
     */
    public function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[\s+]/', '', $phone);
        
        // If starts with 0, replace with 254
        if (strpos($phone, '0') === 0) {
            $phone = '254' . substr($phone, 1);
        }
        
        // If doesn't start with 254, add it
        if (strpos($phone, '254') !== 0) {
            $phone = '254' . $phone;
        }
        
        return $phone;
    }
}

