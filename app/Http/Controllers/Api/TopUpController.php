<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TopUpRequest;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopUpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TopUpRequest::where('user_id', $request->user()->id);

        if ($request->user()->is_admin) {
            $query = TopUpRequest::query();
        }

        $topUps = $query->with('user:id,name,email')
            ->latest()
            ->get();

        return response()->json($topUps);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_code' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $topUp = TopUpRequest::create([
            'user_id' => $request->user()->id,
            'amount' => (int) $validated['amount'],
            'transaction_code' => $validated['transaction_code'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($topUp, 201);
    }

    public function approve(Request $request, TopUpRequest $topup, CreditService $creditService): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($topup->status !== 'pending') {
            return response()->json(['message' => 'This request is not pending.'], 400);
        }

        $creditService->addCredits($topup->user, (int) $topup->amount, 'M-PESA manual top-up: '.$topup->transaction_code);
        $topup->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Top-up approved and credits added.',
            'topup' => $topup->fresh(['user:id,name,email']),
        ]);
    }

    public function reject(Request $request, TopUpRequest $topup): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($topup->status !== 'pending') {
            return response()->json(['message' => 'This request is not pending.'], 400);
        }

        $topup->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Top-up rejected.',
            'topup' => $topup->fresh(['user:id,name,email']),
        ]);
    }
}

