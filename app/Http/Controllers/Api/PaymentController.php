<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopUpRequest;
use App\Services\CreditService;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Initiate M-Pesa STK Push payment
     */
    public function mpesa(Request $request, MpesaService $mpesaService, CreditService $creditService): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:1'],
            'credits' => ['required', 'integer', 'min:1'],
            'package_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $phoneNumber = $mpesaService->formatPhoneNumber($validated['phone_number']);

        // Validate phone number
        if (!$mpesaService->validatePhoneNumber($phoneNumber)) {
            return response()->json([
                'message' => 'Invalid phone number format. Please use Kenya M-Pesa format (e.g., 254712345678)',
            ], 422);
        }

        // For now, create a pending top-up request
        // In production, integrate with M-Pesa STK Push API here
        try {
            // TODO: Implement M-Pesa STK Push
            // $response = $mpesaService->initiateSTKPush([
            //     'phone_number' => $phoneNumber,
            //     'amount' => $validated['amount'],
            //     'account_reference' => 'CREDITS_' . $user->id . '_' . time(),
            //     'transaction_desc' => 'Credit Purchase',
            // ]);
            
            // For now, create a pending top-up request that admin will approve
            $topUp = TopUpRequest::create([
                'user_id' => $user->id,
                'amount' => (int) $validated['credits'],
                'transaction_code' => 'MPESA_PENDING_' . time(),
                'status' => 'pending',
                'notes' => sprintf(
                    'M-Pesa payment: KSh %s for %s credits. Phone: %s. Package: %s',
                    number_format($validated['amount'], 2),
                    $validated['credits'],
                    $phoneNumber,
                    $validated['package_id'] ?? 'custom'
                ),
            ]);

            // In production, you would:
            // 1. Call M-Pesa STK Push API
            // 2. Wait for callback
            // 3. Auto-approve if payment successful
            
            return response()->json([
                'message' => 'Payment request created. Admin will verify and approve your credits.',
                'top_up_id' => $topUp->id,
                'status' => 'pending',
                'note' => 'This is a placeholder. In production, M-Pesa STK Push will be initiated automatically.',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}

