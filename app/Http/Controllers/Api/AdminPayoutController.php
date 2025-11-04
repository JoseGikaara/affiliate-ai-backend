<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Models\Payout;
use App\Models\Affiliate;
use App\Jobs\ProcessPayoutJob;
use App\Services\PayPalService;
use App\Services\MpesaService;
use App\Notifications\PayoutApprovedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AdminPayoutController extends Controller
{
    /**
     * List all payout requests with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = PayoutRequest::with(['affiliate.user', 'payout', 'processedBy']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by affiliate
        if ($request->has('affiliate_id')) {
            $query->where('affiliate_id', $request->affiliate_id);
        }

        $requests = $query->latest()->paginate(20);

        return response()->json([
            'data' => $requests->items()->map(function ($request) {
                return [
                    'id' => $request->id,
                    'affiliate' => [
                        'id' => $request->affiliate->id,
                        'name' => $request->affiliate->name,
                        'email' => $request->affiliate->email,
                    ],
                    'amount' => (float) $request->amount,
                    'currency' => $request->currency,
                    'payout_method' => $request->payout_method,
                    'status' => $request->status,
                    'account_details' => $request->account_details,
                    'admin_notes' => $request->admin_notes,
                    'external_txn_id' => $request->external_txn_id,
                    'processed_by' => $request->processedBy ? [
                        'id' => $request->processedBy->id,
                        'name' => $request->processedBy->name,
                    ] : null,
                    'created_at' => $request->created_at->toISOString(),
                    'updated_at' => $request->updated_at->toISOString(),
                    'payout' => $request->payout ? [
                        'id' => $request->payout->id,
                        'net_amount' => (float) $request->payout->net_amount,
                        'fee' => (float) $request->payout->fee,
                        'external_payout_id' => $request->payout->external_payout_id,
                        'status' => $request->payout->status,
                    ] : null,
                ];
            }),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get payout request details
     */
    public function show(PayoutRequest $payoutRequest): JsonResponse
    {
        $payoutRequest->load(['affiliate.user', 'payout', 'processedBy']);

        return response()->json([
            'id' => $payoutRequest->id,
            'affiliate' => [
                'id' => $payoutRequest->affiliate->id,
                'name' => $payoutRequest->affiliate->name,
                'email' => $payoutRequest->affiliate->email,
                'payout_name' => $payoutRequest->affiliate->payout_name,
                'payout_email' => $payoutRequest->affiliate->payout_email,
                'payout_phone' => $payoutRequest->affiliate->payout_phone,
            ],
            'amount' => (float) $payoutRequest->amount,
            'currency' => $payoutRequest->currency,
            'payout_method' => $payoutRequest->payout_method,
            'status' => $payoutRequest->status,
            'account_details' => $payoutRequest->account_details,
            'admin_notes' => $payoutRequest->admin_notes,
            'external_txn_id' => $payoutRequest->external_txn_id,
            'processed_by' => $payoutRequest->processedBy ? [
                'id' => $payoutRequest->processedBy->id,
                'name' => $payoutRequest->processedBy->name,
            ] : null,
            'created_at' => $payoutRequest->created_at->toISOString(),
            'updated_at' => $payoutRequest->updated_at->toISOString(),
            'payout' => $payoutRequest->payout ? [
                'id' => $payoutRequest->payout->id,
                'net_amount' => (float) $payoutRequest->payout->net_amount,
                'fee' => (float) $payoutRequest->payout->fee,
                'external_payout_id' => $payoutRequest->payout->external_payout_id,
                'status' => $payoutRequest->payout->status,
                'metadata' => $payoutRequest->payout->metadata,
                'attempted_at' => $payoutRequest->payout->attempted_at?->toISOString(),
                'completed_at' => $payoutRequest->payout->completed_at?->toISOString(),
            ] : null,
        ]);
    }

    /**
     * Approve a payout request
     */
    public function approve(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        if ($payoutRequest->status !== 'pending') {
            return response()->json([
                'message' => 'Payout request is not pending',
            ], 422);
        }

        DB::transaction(function () use ($request, $payoutRequest) {
            // Update payout request
            $payoutRequest->update([
                'status' => 'approved',
                'admin_notes' => $request->input('admin_notes'),
                'processed_by' => $request->user()->id,
            ]);

            // Calculate fees
            $fee = 0;
            $netAmount = $payoutRequest->amount;

            if ($payoutRequest->payout_method === 'paypal') {
                $paypalService = new PayPalService();
                $feeCalc = $paypalService->calculateFee((float) $payoutRequest->amount);
                $fee = $feeCalc['fee'];
                $netAmount = $feeCalc['net_amount'];
            } elseif ($payoutRequest->payout_method === 'mpesa') {
                $mpesaService = new MpesaService();
                $feeCalc = $mpesaService->calculateFee((float) $payoutRequest->amount);
                $fee = $feeCalc['fee'];
                $netAmount = $feeCalc['net_amount'];
            }

            // Create payout record
            $payout = Payout::create([
                'payout_request_id' => $payoutRequest->id,
                'affiliate_id' => $payoutRequest->affiliate_id,
                'amount' => $payoutRequest->amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'payout_provider' => $payoutRequest->payout_method,
                'status' => 'processing',
            ]);

            // Dispatch processing job if automation is enabled
            $automated = config('payouts.automated', false);
            if ($automated && in_array($payoutRequest->payout_method, ['paypal', 'mpesa'])) {
                ProcessPayoutJob::dispatch($payoutRequest, $payout);
            }

            // Notify affiliate
            if (config('payouts.notifications.send_on_approval', true)) {
                $payoutRequest->affiliate->user->notify(
                    new PayoutApprovedNotification($payoutRequest)
                );
            }

            return $payout;
        });

        return response()->json([
            'message' => 'Payout request approved successfully',
        ]);
    }

    /**
     * Reject a payout request
     */
    public function reject(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        if (!in_array($payoutRequest->status, ['pending', 'approved'])) {
            return response()->json([
                'message' => 'Payout request cannot be rejected in current status',
            ], 422);
        }

        $validated = $request->validate([
            'admin_notes' => ['required', 'string'],
        ]);

        $payoutRequest->update([
            'status' => 'rejected',
            'admin_notes' => $validated['admin_notes'],
            'processed_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Payout request rejected successfully',
        ]);
    }

    /**
     * Manually trigger payout processing
     */
    public function process(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        if ($payoutRequest->status !== 'approved') {
            return response()->json([
                'message' => 'Payout request must be approved before processing',
            ], 422);
        }

        // Get or create payout
        $payout = $payoutRequest->payout;
        if (!$payout) {
            // Calculate fees
            $fee = 0;
            $netAmount = $payoutRequest->amount;

            if ($payoutRequest->payout_method === 'paypal') {
                $paypalService = new PayPalService();
                $feeCalc = $paypalService->calculateFee((float) $payoutRequest->amount);
                $fee = $feeCalc['fee'];
                $netAmount = $feeCalc['net_amount'];
            } elseif ($payoutRequest->payout_method === 'mpesa') {
                $mpesaService = new MpesaService();
                $feeCalc = $mpesaService->calculateFee((float) $payoutRequest->amount);
                $fee = $feeCalc['fee'];
                $netAmount = $feeCalc['net_amount'];
            }

            $payout = Payout::create([
                'payout_request_id' => $payoutRequest->id,
                'affiliate_id' => $payoutRequest->affiliate_id,
                'amount' => $payoutRequest->amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'payout_provider' => $payoutRequest->payout_method,
                'status' => 'processing',
            ]);
        }

        // Dispatch processing job
        ProcessPayoutJob::dispatch($payoutRequest, $payout);

        return response()->json([
            'message' => 'Payout processing job dispatched',
        ]);
    }

    /**
     * List processed payouts
     */
    public function payouts(Request $request): JsonResponse
    {
        $query = Payout::with(['payoutRequest.affiliate.user', 'affiliate']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by provider
        if ($request->has('provider')) {
            $query->where('payout_provider', $request->provider);
        }

        $payouts = $query->latest()->paginate(20);

        return response()->json([
            'data' => $payouts->items()->map(function ($payout) {
                return [
                    'id' => $payout->id,
                    'payout_request_id' => $payout->payout_request_id,
                    'affiliate' => [
                        'id' => $payout->affiliate->id,
                        'name' => $payout->affiliate->name,
                        'email' => $payout->affiliate->email,
                    ],
                    'amount' => (float) $payout->amount,
                    'fee' => (float) $payout->fee,
                    'net_amount' => (float) $payout->net_amount,
                    'payout_provider' => $payout->payout_provider,
                    'external_payout_id' => $payout->external_payout_id,
                    'status' => $payout->status,
                    'attempted_at' => $payout->attempted_at?->toISOString(),
                    'completed_at' => $payout->completed_at?->toISOString(),
                    'metadata' => $payout->metadata,
                    'created_at' => $payout->created_at->toISOString(),
                ];
            }),
            'pagination' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }
}

