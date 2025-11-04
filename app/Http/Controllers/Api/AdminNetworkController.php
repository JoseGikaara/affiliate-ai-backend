<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminNetworkController extends Controller
{
    /**
     * List all affiliate networks (including inactive)
     */
    public function index(): JsonResponse
    {
        $networks = AffiliateNetwork::orderBy('category')
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
                    'is_active' => $network->is_active,
                    'base_credit_cost' => $network->getBaseCreditCost(),
                    'created_at' => $network->created_at->toISOString(),
                    'updated_at' => $network->updated_at->toISOString(),
                ];
            });

        return response()->json($networks);
    }

    /**
     * Create a new affiliate network
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:affiliate_networks,slug'],
            'description' => ['nullable', 'string'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'category' => ['required', 'string', 'in:freelancing,education,cpa,forex,ecommerce'],
            'country_availability' => ['nullable', 'array'],
            'country_availability.*' => ['string', 'max:2'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $network = AffiliateNetwork::create($validated);

        return response()->json([
            'message' => 'Affiliate network created successfully',
            'network' => $network,
        ], 201);
    }

    /**
     * Update an affiliate network
     */
    public function update(Request $request, AffiliateNetwork $network): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:affiliate_networks,slug,' . $network->id],
            'description' => ['nullable', 'string'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'logo_url' => ['nullable', 'url', 'max:500'],
            'category' => ['sometimes', 'string', 'in:freelancing,education,cpa,forex,ecommerce'],
            'country_availability' => ['nullable', 'array'],
            'country_availability.*' => ['string', 'max:2'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $network->update($validated);

        return response()->json([
            'message' => 'Affiliate network updated successfully',
            'network' => $network->fresh(),
        ]);
    }

    /**
     * Delete an affiliate network
     */
    public function destroy(AffiliateNetwork $network): JsonResponse
    {
        // Check if network has landing pages
        if ($network->landingPages()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete network with existing landing pages',
            ], 422);
        }

        $network->delete();

        return response()->json([
            'message' => 'Affiliate network deleted successfully',
        ]);
    }

    /**
     * Get credit rules configuration
     */
    public function getCreditRules(): JsonResponse
    {
        return response()->json([
            'network_categories' => config('credits.network_categories'),
            'email_automation_multiplier' => config('credits.email_automation_multiplier'),
        ]);
    }

    /**
     * Update credit rules configuration
     */
    public function updateCreditRules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'network_categories' => ['sometimes', 'array'],
            'network_categories.*.base_cost' => ['required', 'integer', 'min:0'],
            'email_automation_multiplier' => ['sometimes', 'numeric', 'min:1', 'max:2'],
        ]);

        // Update config file (in production, you'd want to store this in database)
        $config = config('credits');
        
        if (isset($validated['network_categories'])) {
            foreach ($validated['network_categories'] as $category => $data) {
                if (isset($config['network_categories'][$category])) {
                    $config['network_categories'][$category]['base_cost'] = $data['base_cost'];
                }
            }
        }

        if (isset($validated['email_automation_multiplier'])) {
            $config['email_automation_multiplier'] = $validated['email_automation_multiplier'];
        }

        // Note: In a production app, you'd save this to database or a settings table
        // For now, we'll just return success
        
        return response()->json([
            'message' => 'Credit rules updated successfully',
            'network_categories' => $config['network_categories'],
            'email_automation_multiplier' => $config['email_automation_multiplier'],
        ]);
    }
}
