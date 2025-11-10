<?php

namespace App\Http\Controllers\Modules\Dropservicing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Modules\Dropservicing\Service;
use App\Models\Modules\Dropservicing\ServiceCategory;
use App\Models\Modules\Dropservicing\UserGig;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DropservicingGigController extends Controller
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $gigs = UserGig::with(['service', 'service.category'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($gig) {
                return $this->formatGig($gig);
            });

        return response()->json($gigs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'exists:services,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'pricing_tiers' => ['required', 'array'],
            'pricing_tiers.basic' => ['required', 'array'],
            'pricing_tiers.standard' => ['required', 'array'],
            'pricing_tiers.premium' => ['required', 'array'],
            'paypal_email' => ['required', 'email'],
        ]);

        $user = $request->user();
        $service = Service::findOrFail($validated['service_id']);

        // Check credits (20 credits to create a gig)
        $gigCreationCost = 20;
        if (!$this->creditService->hasEnoughCredits($user, $gigCreationCost)) {
            return response()->json([
                'message' => 'Insufficient credits. You need 20 credits to create a gig.',
            ], 402);
        }

        // Generate unique slug
        $baseSlug = Str::slug($validated['title']);
        $slug = $baseSlug;
        $counter = 1;
        while (UserGig::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $gig = UserGig::create([
            'user_id' => $user->id,
            'service_id' => $validated['service_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'pricing_tiers' => $validated['pricing_tiers'],
            'paypal_email' => $validated['paypal_email'],
            'status' => 'draft',
            'slug' => $slug,
        ]);

        // Deduct credits
        $this->creditService->deductCredits($user, $gigCreationCost, 'Gig creation: ' . $validated['title']);

        return response()->json([
            'message' => 'Gig created successfully',
            'gig' => $this->formatGig($gig->fresh(['service', 'service.category'])),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $gig = UserGig::with(['service', 'service.category', 'user', 'orders'])
            ->findOrFail($id);

        return response()->json($this->formatGig($gig));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $gig = UserGig::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'pricing_tiers' => ['sometimes', 'array'],
            'paypal_email' => ['sometimes', 'email'],
            'status' => ['sometimes', 'in:draft,active,inactive'],
        ]);

        $gig->update($validated);

        return response()->json([
            'message' => 'Gig updated successfully',
            'gig' => $this->formatGig($gig->fresh(['service', 'service.category'])),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $gig = UserGig::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $gig->delete();

        return response()->json([
            'message' => 'Gig deleted successfully',
        ]);
    }

    /**
     * Format gig for API response
     */
    protected function formatGig(UserGig $gig): array
    {
        return [
            'id' => $gig->id,
            'title' => $gig->title,
            'description' => $gig->description,
            'slug' => $gig->slug,
            'status' => $gig->status,
            'pricing_tiers' => $gig->pricing_tiers,
            'paypal_email' => $gig->paypal_email,
            'service' => $gig->service ? [
                'id' => $gig->service->id,
                'title' => $gig->service->title,
                'description' => $gig->service->description,
                'base_credit_cost' => $gig->service->base_credit_cost,
                'category' => $gig->service->category ? [
                    'id' => $gig->service->category->id,
                    'name' => $gig->service->category->name,
                ] : null,
            ] : null,
            'orders_count' => $gig->orders()->count(),
            'created_at' => $gig->created_at->toISOString(),
            'updated_at' => $gig->updated_at->toISOString(),
        ];
    }
}
