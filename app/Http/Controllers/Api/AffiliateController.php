<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateLink;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    /**
     * Get current user's affiliate profile
     */
    public function getMyAffiliate(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $affiliate = Affiliate::where('user_id', $user->id)->first();
        
        if (!$affiliate) {
            return response()->json([
                'message' => 'No affiliate profile found',
                'affiliate' => null,
            ], 404);
        }

        return response()->json([
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'email' => $affiliate->email,
                'referral_id' => $affiliate->referral_id,
                'commission_rate' => (float) $affiliate->commission_rate,
                'total_clicks' => $affiliate->total_clicks,
                'total_conversions' => $affiliate->total_conversions,
                'commission_earned' => (float) $affiliate->commission_earned,
                'status' => $affiliate->status,
                'created_at' => $affiliate->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get current user's affiliate dashboard stats
     */
    public function getMyDashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $affiliate = Affiliate::where('user_id', $user->id)->first();
        
        if (!$affiliate) {
            return response()->json([
                'message' => 'No affiliate profile found',
            ], 404);
        }

        return $this->getDashboardStats($request, $affiliate->id);
    }

    /**
     * Generate a tracking link for an affiliate and offer
     */
    public function generateLink(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        // Get affiliate (must belong to the authenticated user or be admin)
        $affiliate = Affiliate::findOrFail($id);
        
        if (!$user->is_admin && $affiliate->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'offer_id' => ['required', 'exists:offers,id'],
            'destination_url' => ['sometimes', 'url', 'max:500'],
        ]);

        $offer = Offer::findOrFail($validated['offer_id']);

        // Check if link already exists
        $existingLink = AffiliateLink::where('affiliate_id', $affiliate->id)
            ->where('offer_id', $offer->id)
            ->first();

        if ($existingLink) {
            return response()->json([
                'message' => 'Link already exists',
                'link' => [
                    'id' => $existingLink->id,
                    'tracking_id' => $existingLink->tracking_id,
                    'tracking_link' => $existingLink->tracking_link,
                    'total_clicks' => $existingLink->total_clicks,
                    'total_conversions' => $existingLink->total_conversions,
                    'offer' => [
                        'id' => $offer->id,
                        'name' => $offer->name,
                    ],
                ],
            ]);
        }

        // Generate unique tracking ID
        $trackingId = 'TRK' . strtoupper(Str::random(12));
        
        // Ensure uniqueness
        while (AffiliateLink::where('tracking_id', $trackingId)->exists()) {
            $trackingId = 'TRK' . strtoupper(Str::random(12));
        }

        // Build tracking link
        $baseUrl = config('app.url', 'http://localhost');
        $trackingLink = $baseUrl . '/track/' . $trackingId . '?ref=' . $affiliate->referral_id;

        // If destination URL is provided, append it
        if (isset($validated['destination_url'])) {
            $trackingLink .= '&dest=' . urlencode($validated['destination_url']);
        } else {
            // Use default offer URL or a placeholder
            $trackingLink .= '&dest=' . urlencode($offer->image_url ?? $baseUrl);
        }

        $affiliateLink = AffiliateLink::create([
            'affiliate_id' => $affiliate->id,
            'offer_id' => $offer->id,
            'tracking_id' => $trackingId,
            'tracking_link' => $trackingLink,
        ]);

        return response()->json([
            'message' => 'Tracking link generated successfully',
            'link' => [
                'id' => $affiliateLink->id,
                'tracking_id' => $affiliateLink->tracking_id,
                'tracking_link' => $affiliateLink->tracking_link,
                'total_clicks' => $affiliateLink->total_clicks,
                'total_conversions' => $affiliateLink->total_conversions,
                'offer' => [
                    'id' => $offer->id,
                    'name' => $offer->name,
                ],
            ],
        ], 201);
    }

    /**
     * Get all links for an affiliate
     */
    public function getLinks(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        $affiliate = Affiliate::findOrFail($id);
        
        if (!$user->is_admin && $affiliate->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $links = AffiliateLink::where('affiliate_id', $affiliate->id)
            ->with('offer')
            ->latest()
            ->get()
            ->map(function ($link) {
                return [
                    'id' => $link->id,
                    'tracking_id' => $link->tracking_id,
                    'tracking_link' => $link->tracking_link,
                    'total_clicks' => $link->total_clicks,
                    'total_conversions' => $link->total_conversions,
                    'offer' => [
                        'id' => $link->offer->id,
                        'name' => $link->offer->name,
                        'payout' => (float) $link->offer->payout,
                    ],
                    'created_at' => $link->created_at->toISOString(),
                ];
            });

        return response()->json($links);
    }

    /**
     * Get active offers for affiliates
     */
    public function getOffers(Request $request): JsonResponse
    {
        $offers = Offer::where('status', 'active')
            ->select('id', 'name', 'description', 'payout', 'commission_rate', 'status', 'image_url')
            ->latest()
            ->get()
            ->map(function ($offer) {
                return [
                    'id' => $offer->id,
                    'name' => $offer->name,
                    'description' => $offer->description,
                    'payout' => (float) $offer->payout,
                    'commission_rate' => (float) $offer->commission_rate,
                    'status' => $offer->status,
                    'image_url' => $offer->image_url,
                ];
            });

        return response()->json($offers);
    }

    /**
     * Get affiliate dashboard stats
     */
    public function getDashboardStats(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        $affiliate = Affiliate::findOrFail($id);
        
        if (!$user->is_admin && $affiliate->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Calculate stats
        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        // Optimize: Use single query with conditional aggregation
        $earnings = \App\Models\Commission::where('affiliate_id', $affiliate->id)
            ->where('status', 'approved')
            ->selectRaw('
                SUM(CASE WHEN DATE(date) = DATE(?) THEN amount ELSE 0 END) as today,
                SUM(CASE WHEN date >= ? THEN amount ELSE 0 END) as week,
                SUM(CASE WHEN date >= ? THEN amount ELSE 0 END) as month
            ', [$today->toDateString(), $weekStart, $monthStart])
            ->first();

        $todayEarnings = (float) ($earnings->today ?? 0);
        $weekEarnings = (float) ($earnings->week ?? 0);
        $monthEarnings = (float) ($earnings->month ?? 0);

        // Top performing offers
        $topOffers = \App\Models\AffiliateLink::where('affiliate_id', $affiliate->id)
            ->with('offer')
            ->orderBy('total_conversions', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($link) {
                return [
                    'offer_name' => $link->offer->name,
                    'clicks' => $link->total_clicks,
                    'conversions' => $link->total_conversions,
                    'conversion_rate' => $link->total_clicks > 0 
                        ? round(($link->total_conversions / $link->total_clicks) * 100, 2)
                        : 0,
                ];
            });

        // Recent conversions
        $recentConversions = \App\Models\Conversion::where('affiliate_id', $affiliate->id)
            ->with(['offer', 'commission'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function ($conversion) {
                return [
                    'id' => $conversion->id,
                    'offer_name' => $conversion->offer->name,
                    'conversion_value' => (float) $conversion->conversion_value,
                    'commission_amount' => $conversion->commission ? (float) $conversion->commission->amount : 0,
                    'status' => $conversion->status,
                    'created_at' => $conversion->created_at->toISOString(),
                ];
            });

        // Conversions over time (last 30 days)
        $conversionsData = \App\Models\Conversion::where('affiliate_id', $affiliate->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'conversions' => (int) $item->count,
                ];
            });

        return response()->json([
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'referral_id' => $affiliate->referral_id,
                'total_clicks' => $affiliate->total_clicks,
                'total_conversions' => $affiliate->total_conversions,
                'commission_earned' => (float) $affiliate->commission_earned,
            ],
            'earnings' => [
                'today' => (float) $todayEarnings,
                'week' => (float) $weekEarnings,
                'month' => (float) $monthEarnings,
            ],
            'top_offers' => $topOffers,
            'recent_conversions' => $recentConversions,
            'conversions_over_time' => $conversionsData,
        ]);
    }
}

