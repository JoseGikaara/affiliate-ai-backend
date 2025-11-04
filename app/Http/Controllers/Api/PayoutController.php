<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\PayoutRequest;
use App\Models\Payout;
use App\Notifications\PayoutRequestedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class PayoutController extends Controller
{
    /**
     * Get affiliate's available balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $affiliate = Affiliate::where('user_id', $user->id)->first();

        if (!$affiliate) {
            return response()->json([
                'message' => 'Affiliate profile not found',
            ], 404);
        }

        // Calculate available balance (total earned - pending/approved requests)
        $pendingAmount = PayoutRequest::where('affiliate_id', $affiliate->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');

        $availableBalance = $affiliate->commission_earned - $pendingAmount;

        return response()->json([
            'total_earned' => (float) $affiliate->commission_earned,
            'pending_amount' => (float) $pendingAmount,
            'available_balance' => max(0, (float) $availableBalance),
            'currency' => 'USD', // Can be configured later
        ]);
    }

    /**
     * List affiliate's payout requests
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $affiliate = Affiliate::where('user_id', $user->id)->first();

        if (!$affiliate) {
            return response()->json([
                'message' => 'Affiliate profile not found',
            ], 404);
        }

        $requests = PayoutRequest::where('affiliate_id', $affiliate->id)
            ->with('payout')
            ->latest()
            ->get()
            ->map(function ($request) {
                return [
                    'id' => $request->id,
                    'amount' => (float) $request->amount,
                    'currency' => $request->currency,
                    'payout_method' => $request->payout_method,
                    'status' => $request->status,
                    'external_txn_id' => $request->external_txn_id,
                    'admin_notes' => $request->admin_notes,
                    'created_at' => $request->created_at->toISOString(),
                    'updated_at' => $request->updated_at->toISOString(),
                    'payout' => $request->payout ? [
                        'id' => $request->payout->id,
                        'net_amount' => (float) $request->payout->net_amount,
                        'fee' => (float) $request->payout->fee,
                        'external_payout_id' => $request->payout->external_payout_id,
                        'status' => $request->payout->status,
                        'completed_at' => $request->payout->completed_at?->toISOString(),
                    ] : null,
                ];
            });

        return response()->json($requests);
    }

    /**
     * Create a new payout request
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $affiliate = Affiliate::where('user_id', $user->id)->first();

        if (!$affiliate) {
            return response()->json([
                'message' => 'Affiliate profile not found',
            ], 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payout_method' => ['required', 'string', 'in:paypal,mpesa,manual'],
            'account_details' => ['required', 'array'],
            'account_details.email' => ['required_if:payout_method,paypal', 'email'],
            'account_details.phone' => ['required_if:payout_method,mpesa', 'string'],
            'account_details.account_number' => ['required_if:payout_method,manual', 'string'],
        ]);

        $minAmount = config('payouts.min_amount', 500);
        if ($validated['amount'] < $minAmount) {
            return response()->json([
                'message' => "Minimum payout amount is {$minAmount}",
            ], 422);
        }

        // Check available balance
        $pendingAmount = PayoutRequest::where('affiliate_id', $affiliate->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');

        $availableBalance = $affiliate->commission_earned - $pendingAmount;

        if ($validated['amount'] > $availableBalance) {
            return response()->json([
                'message' => 'Insufficient balance. Available: ' . number_format($availableBalance, 2),
            ], 422);
        }

        // Check daily limit
        $todayRequests = PayoutRequest::where('affiliate_id', $affiliate->id)
            ->whereDate('created_at', today())
            ->count();

        $maxPerDay = config('payouts.max_per_day', 3);
        if ($todayRequests >= $maxPerDay) {
            return response()->json([
                'message' => "Maximum {$maxPerDay} payout requests allowed per day",
            ], 422);
        }

        // Create payout request
        $payoutRequest = PayoutRequest::create([
            'affiliate_id' => $affiliate->id,
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? config('payouts.default_currency', 'USD'),
            'payout_method' => $validated['payout_method'],
            'account_details' => $validated['account_details'],
            'status' => 'pending',
        ]);

        // Send notifications
        if (config('payouts.notifications.send_on_request', true)) {
            // Notify affiliate
            $user->notify(new PayoutRequestedNotification($payoutRequest));

            // Notify admins
            $adminEmails = config('payouts.notifications.admin_emails', []);
            if (!empty($adminEmails)) {
                // Get admin users to notify
                $admins = \App\Models\User::where('is_admin', true)->get();
                Notification::send($admins, new PayoutRequestedNotification($payoutRequest));
            }
        }

        return response()->json([
            'message' => 'Payout request created successfully',
            'payout_request' => [
                'id' => $payoutRequest->id,
                'amount' => (float) $payoutRequest->amount,
                'currency' => $payoutRequest->currency,
                'payout_method' => $payoutRequest->payout_method,
                'status' => $payoutRequest->status,
                'created_at' => $payoutRequest->created_at->toISOString(),
            ],
        ], 201);
    }
}

