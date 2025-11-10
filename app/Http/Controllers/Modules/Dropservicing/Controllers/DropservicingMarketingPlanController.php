<?php

namespace App\Http\Controllers\Modules\Dropservicing\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMarketingPlanJob;
use App\Models\DropservicingMarketingPlan;
use App\Models\Modules\Dropservicing\UserGig;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DropservicingMarketingPlanController extends Controller
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * Display a listing of user's marketing plans
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $plans = DropservicingMarketingPlan::with(['gig', 'fulfillmentLog'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($plan) {
                return $this->formatPlan($plan);
            });

        return response()->json($plans);
    }

    /**
     * Store a newly created marketing plan
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gig_id' => ['nullable', 'exists:user_gigs,id'],
            'plan_type' => ['required', 'in:7-day,30-day,ads-only,content-calendar'],
            'input_summary' => ['required', 'array'],
            'input_summary.audience' => ['required', 'string'],
            'input_summary.platforms' => ['required', 'array'],
            'input_summary.goal' => ['required', 'string'],
            'input_summary.budget' => ['nullable', 'string'],
            'input_summary.tone' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        // Rate limiting: max 3 plans per user per 24 hours
        $maxPlansPerDay = config('dropservicing.max_plans_per_day', 3);
        $key = 'marketing_plans:' . $user->id;
        
        if (RateLimiter::tooManyAttempts($key, $maxPlansPerDay)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "Too many marketing plan requests. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($key, 86400); // 24 hours

        // Verify gig belongs to user if provided
        if (isset($validated['gig_id'])) {
            $gig = UserGig::where('user_id', $user->id)
                ->findOrFail($validated['gig_id']);
        }

        // Get credit cost for plan type
        $creditCost = DropservicingMarketingPlan::getCreditCost($validated['plan_type']);

        // Check if user has enough credits
        if (!$this->creditService->hasEnoughCredits($user, $creditCost)) {
            return response()->json([
                'message' => 'Insufficient credits. You need ' . $creditCost . ' credits to generate this marketing plan.',
                'required_credits' => $creditCost,
                'available_credits' => $user->credits,
            ], 402);
        }

        // Deduct credits immediately
        $this->creditService->deductCredits($user, $creditCost, 'Marketing plan generation: ' . $validated['plan_type']);

        // Create plan
        $plan = DropservicingMarketingPlan::create([
            'user_id' => $user->id,
            'gig_id' => $validated['gig_id'] ?? null,
            'plan_type' => $validated['plan_type'],
            'input_summary' => $validated['input_summary'],
            'status' => 'pending',
            'credit_cost' => $creditCost,
        ]);

        // Dispatch job to generate plan
        ProcessMarketingPlanJob::dispatch($plan->id);

        return response()->json([
            'message' => 'Marketing plan generation started',
            'plan' => $this->formatPlan($plan->fresh(['gig'])),
        ], 201);
    }

    /**
     * Display the specified marketing plan
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $plan = DropservicingMarketingPlan::with(['gig', 'fulfillmentLog'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json($this->formatPlan($plan));
    }

    /**
     * Regenerate a marketing plan (deducts credits again)
     */
    public function regenerate(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $plan = DropservicingMarketingPlan::where('user_id', $user->id)
            ->findOrFail($id);

        // Get credit cost
        $creditCost = DropservicingMarketingPlan::getCreditCost($plan->plan_type);

        // Check credits
        if (!$this->creditService->hasEnoughCredits($user, $creditCost)) {
            return response()->json([
                'message' => 'Insufficient credits. You need ' . $creditCost . ' credits to regenerate this plan.',
                'required_credits' => $creditCost,
                'available_credits' => $user->credits,
            ], 402);
        }

        // Deduct credits
        $this->creditService->deductCredits($user, $creditCost, 'Marketing plan regeneration: ' . $plan->plan_type);

        // Reset plan
        $plan->update([
            'ai_output' => null,
            'status' => 'pending',
            'credit_cost' => $creditCost,
            'tokens_used' => null,
        ]);

        // Delete old fulfillment log
        $plan->fulfillmentLog?->delete();

        // Dispatch job
        ProcessMarketingPlanJob::dispatch($plan->id);

        return response()->json([
            'message' => 'Marketing plan regeneration started',
            'plan' => $this->formatPlan($plan->fresh(['gig'])),
        ]);
    }

    /**
     * Admin: List all marketing plans
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $plans = DropservicingMarketingPlan::with(['user', 'gig', 'fulfillmentLog'])
            ->latest()
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'user' => [
                        'id' => $plan->user->id,
                        'name' => $plan->user->name,
                        'email' => $plan->user->email,
                    ],
                    'gig_id' => $plan->gig_id,
                    'gig_title' => $plan->gig->title ?? null,
                    'plan_type' => $plan->plan_type,
                    'status' => $plan->status,
                    'credit_cost' => $plan->credit_cost,
                    'tokens_used' => $plan->tokens_used,
                    'created_at' => $plan->created_at->toISOString(),
                ];
            });

        return response()->json($plans);
    }

    /**
     * Admin: Show specific marketing plan
     */
    public function adminShow(Request $request, string $id): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $plan = DropservicingMarketingPlan::with(['user', 'gig', 'fulfillmentLog'])
            ->findOrFail($id);

        return response()->json($this->formatPlan($plan));
    }

    /**
     * Admin: Re-run marketing plan (without charging user)
     */
    public function adminRerun(Request $request, string $id): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $plan = DropservicingMarketingPlan::findOrFail($id);

        // Reset plan without deducting credits
        $plan->update([
            'ai_output' => null,
            'status' => 'pending',
            'tokens_used' => null,
        ]);

        // Delete old fulfillment log
        $plan->fulfillmentLog?->delete();

        // Dispatch job
        ProcessMarketingPlanJob::dispatch($plan->id);

        return response()->json([
            'message' => 'Marketing plan regeneration started (admin-triggered, no charge)',
            'plan' => $this->formatPlan($plan->fresh(['gig'])),
        ]);
    }

    /**
     * Format plan for API response
     */
    protected function formatPlan(DropservicingMarketingPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'gig_id' => $plan->gig_id,
            'gig_title' => $plan->gig->title ?? null,
            'plan_type' => $plan->plan_type,
            'input_summary' => $plan->input_summary,
            'ai_output' => $plan->ai_output,
            'status' => $plan->status,
            'credit_cost' => $plan->credit_cost,
            'tokens_used' => $plan->tokens_used,
            'fulfillment_log' => $plan->fulfillmentLog ? [
                'ai_model' => $plan->fulfillmentLog->ai_model,
                'tokens_used' => $plan->fulfillmentLog->tokens_used,
                'success' => $plan->fulfillmentLog->success,
            ] : null,
            'created_at' => $plan->created_at->toISOString(),
            'updated_at' => $plan->updated_at->toISOString(),
        ];
    }
}
