<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateLink;
use App\Models\AffiliateOfferRate;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversionController extends Controller
{
    /**
     * Track a conversion
     */
    public function track(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'tracking_id' => ['required', 'string', 'max:100'],
                'offer_id' => ['required', 'exists:offers,id'],
                'conversion_value' => ['required', 'numeric', 'min:0'],
                'metadata' => ['sometimes', 'array'],
            ]);

            // Find affiliate link by tracking_id
            $affiliateLink = AffiliateLink::where('tracking_id', $validated['tracking_id'])
                ->with(['affiliate', 'offer'])
                ->first();

            if (!$affiliateLink) {
                Log::warning('Conversion tracking failed: Invalid tracking_id', [
                    'tracking_id' => $validated['tracking_id'],
                    'ip' => $request->ip(),
                ]);
                
                return response()->json([
                    'message' => 'Invalid tracking ID',
                ], 404);
            }

            // Verify offer matches
            if ($affiliateLink->offer_id != $validated['offer_id']) {
                Log::warning('Conversion tracking failed: Offer mismatch', [
                    'tracking_id' => $validated['tracking_id'],
                    'link_offer_id' => $affiliateLink->offer_id,
                    'provided_offer_id' => $validated['offer_id'],
                ]);
                
                return response()->json([
                    'message' => 'Offer mismatch',
                ], 400);
            }

            $affiliate = $affiliateLink->affiliate;
            $offer = $affiliateLink->offer;

            // Start transaction
            DB::beginTransaction();

            try {
                // Create conversion
                $conversion = Conversion::create([
                    'affiliate_id' => $affiliate->id,
                    'offer_id' => $offer->id,
                    'affiliate_link_id' => $affiliateLink->id,
                    'tracking_id' => $validated['tracking_id'],
                    'conversion_value' => $validated['conversion_value'],
                    'status' => 'approved', // Auto-approve for now
                    'metadata' => $validated['metadata'] ?? null,
                ]);

                // Calculate commission rate (check for custom rate first)
                $customRate = AffiliateOfferRate::where('affiliate_id', $affiliate->id)
                    ->where('offer_id', $offer->id)
                    ->first();

                $payoutRate = $customRate 
                    ? $customRate->commission_rate 
                    : ($offer->commission_rate ?? $affiliate->commission_rate ?? 10.00);

                // Calculate commission
                $commissionAmount = ($payoutRate / 100) * $validated['conversion_value'];

                // Create commission record
                $commission = Commission::create([
                    'affiliate_id' => $affiliate->id,
                    'offer_id' => $offer->id,
                    'conversion_id' => $conversion->id,
                    'amount' => $commissionAmount,
                    'payout_rate' => $payoutRate,
                    'conversion_value' => $validated['conversion_value'],
                    'date' => now(),
                    'status' => 'approved',
                ]);

                // Update affiliate stats
                $affiliate->increment('total_conversions');
                $affiliate->increment('commission_earned', $commissionAmount);

                // Update affiliate link stats
                $affiliateLink->increment('total_conversions');

                DB::commit();

                Log::info('Conversion tracked successfully', [
                    'conversion_id' => $conversion->id,
                    'affiliate_id' => $affiliate->id,
                    'commission_amount' => $commissionAmount,
                ]);

                return response()->json([
                    'message' => 'Conversion tracked successfully',
                    'conversion' => [
                        'id' => $conversion->id,
                        'commission_amount' => (float) $commissionAmount,
                        'status' => $conversion->status,
                    ],
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Conversion tracking error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to track conversion',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Track a click (public endpoint, no auth required)
     */
    public function trackClick(Request $request, $trackingId): JsonResponse
    {
        try {
            $affiliateLink = AffiliateLink::where('tracking_id', $trackingId)->first();

            if (!$affiliateLink) {
                return response()->json([
                    'message' => 'Invalid tracking ID',
                ], 404);
            }

            // Update click count
            $affiliateLink->increment('total_clicks');
            $affiliateLink->affiliate->increment('total_clicks');

            // Return redirect URL
            $destination = $request->query('dest');
            $redirectUrl = $destination ?? $affiliateLink->offer->image_url ?? config('app.url');

            return response()->json([
                'redirect_url' => $redirectUrl,
                'tracking_id' => $trackingId,
            ]);

        } catch (\Exception $e) {
            Log::error('Click tracking error', [
                'error' => $e->getMessage(),
                'tracking_id' => $trackingId,
            ]);

            return response()->json([
                'message' => 'Failed to track click',
            ], 500);
        }
    }
}

