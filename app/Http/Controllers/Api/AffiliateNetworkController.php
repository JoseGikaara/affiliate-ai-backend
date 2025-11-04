<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateNetwork;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AffiliateNetworkController extends Controller
{
    protected CreditService $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * List all active affiliate networks
     */
    public function index(): JsonResponse
    {
        $networks = AffiliateNetwork::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(function ($network) {
                return [
                    'id' => $network->id,
                    'name' => $network->name,
                    'slug' => $network->slug,
                    'description' => $network->description,
                    'base_url' => $network->base_url,
                    'logo_url' => $network->logo_url,
                    'category' => $network->category,
                    'country_availability' => $network->country_availability,
                    'learn_more_credit_cost' => $network->learn_more_credit_cost ?? 3,
                ];
            });

        return response()->json($networks);
    }

    /**
     * Get a specific network (basic info, no credits required)
     */
    public function show(AffiliateNetwork $network): JsonResponse
    {
        if (!$network->is_active) {
            return response()->json(['message' => 'Network not found'], 404);
        }

        return response()->json([
            'id' => $network->id,
            'name' => $network->name,
            'slug' => $network->slug,
            'description' => $network->description,
            'base_url' => $network->base_url,
            'logo_url' => $network->logo_url,
            'category' => $network->category,
            'country_availability' => $network->country_availability,
            'base_credit_cost' => $network->getBaseCreditCost(),
            'learn_more_credit_cost' => $network->learn_more_credit_cost ?? 3,
        ]);
    }

    /**
     * Learn more about a network (requires credits)
     */
    public function learnMore(Request $request, AffiliateNetwork $network): JsonResponse
    {
        if (!$network->is_active) {
            return response()->json(['message' => 'Network not found'], 404);
        }

        $user = $request->user();
        $creditCost = $network->learn_more_credit_cost ?? 3;

        // Check if user has enough credits
        if (!$this->creditService->hasEnoughCredits($user, $creditCost)) {
            throw ValidationException::withMessages([
                'credits' => [
                    'Insufficient credits. You need ' . $creditCost . ' credits to learn more about this network.',
                ],
            ]);
        }

        // Deduct credits and return detailed information
        DB::transaction(function () use ($user, $network, $creditCost) {
            $this->creditService->deductCredits(
                $user,
                $creditCost,
                "Learned more about network: {$network->name}"
            );
        });

        return response()->json([
            'id' => $network->id,
            'name' => $network->name,
            'slug' => $network->slug,
            'description' => $network->description,
            'detailed_description' => $network->detailed_description,
            'base_url' => $network->base_url,
            'registration_url' => $network->registration_url,
            'logo_url' => $network->logo_url,
            'category' => $network->category,
            'commission_rate' => $network->commission_rate,
            'payment_methods' => $network->payment_methods ?? [],
            'minimum_payout' => $network->minimum_payout,
            'payout_frequency' => $network->payout_frequency,
            'features' => $network->features ?? [],
            'pros' => $network->pros ?? [],
            'cons' => $network->cons ?? [],
            'country_availability' => $network->country_availability,
            'base_credit_cost' => $network->getBaseCreditCost(),
            'learn_more_credit_cost' => $creditCost,
            'remaining_credits' => $user->fresh()->credits,
        ]);
    }
}
