<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CreditPackageController extends Controller
{
    /**
     * Get available credit packages
     */
    public function index(): JsonResponse
    {
        $packages = [
            [
                'id' => 'starter',
                'name' => 'Starter Pack',
                'credits' => 100,
                'price' => 750, // KSh
                'price_usd' => 5.00,
                'popular' => false,
                'features' => ['100 Credits', 'Basic Analytics', 'Email Support'],
            ],
            [
                'id' => 'pro',
                'name' => 'Pro Pack',
                'credits' => 500,
                'price' => 2800, // KSh
                'price_usd' => 18.50,
                'popular' => true,
                'features' => ['500 Credits', 'Advanced Analytics', 'Priority Support', 'Best Value'],
            ],
            [
                'id' => 'power',
                'name' => 'Power Pack',
                'credits' => 2000,
                'price' => 8500, // KSh
                'price_usd' => 56.50,
                'popular' => false,
                'features' => ['2000 Credits', 'Premium Analytics', '24/7 Support', 'Custom Features'],
            ],
        ];

        return response()->json([
            'packages' => $packages,
            'currency' => 'KES',
            'exchange_rate' => 150, // KSh to USD (approximate)
        ]);
    }
}

