<?php

namespace App\Http\Controllers\Modules\Dropservicing\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\FulfillGigJob;
use App\Models\Modules\Dropservicing\GigOrder;
use App\Models\Modules\Dropservicing\UserGig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DropservicingOrderController extends Controller
{
    /**
     * Display a listing of orders for the authenticated user's gigs.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $orders = GigOrder::with(['gig', 'gig.service', 'fulfillment'])
            ->whereHas('gig', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->latest()
            ->get()
            ->map(function ($order) {
                return $this->formatOrder($order);
            });

        return response()->json($orders);
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'gig_id' => ['required', 'exists:user_gigs,id'],
            'buyer_email' => ['required', 'email'],
            'requirements' => ['required', 'string'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'pricing_tier' => ['required', 'string', 'in:basic,standard,premium'],
            'paypal_transaction_id' => ['nullable', 'string'],
        ]);

        $gig = UserGig::findOrFail($validated['gig_id']);

        // Verify gig is active
        if ($gig->status !== 'active') {
            return response()->json([
                'message' => 'This gig is not currently active.',
            ], 422);
        }

        // Get price from pricing tier
        $pricingTiers = $gig->pricing_tiers;
        if (!isset($pricingTiers[$validated['pricing_tier']])) {
            return response()->json([
                'message' => 'Invalid pricing tier selected.',
            ], 422);
        }

        $order = GigOrder::create([
            'gig_id' => $gig->id,
            'buyer_email' => $validated['buyer_email'],
            'requirements' => $validated['requirements'],
            'total_price' => $validated['total_price'],
            'status' => 'pending',
            'paypal_transaction_id' => $validated['paypal_transaction_id'] ?? null,
        ]);

        // Dispatch fulfillment job
        FulfillGigJob::dispatch($order->id);

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $this->formatOrder($order->fresh(['gig', 'fulfillment'])),
        ], 201);
    }

    /**
     * Trigger fulfillment for an order.
     */
    public function fulfill(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        
        $order = GigOrder::with(['gig'])
            ->whereHas('gig', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->findOrFail($orderId);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Order cannot be fulfilled. Current status: ' . $order->status,
            ], 422);
        }

        // Dispatch fulfillment job
        FulfillGigJob::dispatch($order->id);

        return response()->json([
            'message' => 'Fulfillment job dispatched',
            'order' => $this->formatOrder($order->fresh(['gig', 'fulfillment'])),
        ]);
    }

    /**
     * Format order for API response
     */
    protected function formatOrder(GigOrder $order): array
    {
        return [
            'id' => $order->id,
            'gig_id' => $order->gig_id,
            'gig_title' => $order->gig->title ?? null,
            'buyer_email' => $order->buyer_email,
            'requirements' => $order->requirements,
            'total_price' => (float) $order->total_price,
            'status' => $order->status,
            'paypal_transaction_id' => $order->paypal_transaction_id,
            'fulfillment' => $order->fulfillment ? [
                'id' => $order->fulfillment->id,
                'ai_output' => $order->fulfillment->ai_output,
                'file_url' => $order->fulfillment->file_url,
                'created_at' => $order->fulfillment->created_at->toISOString(),
            ] : null,
            'created_at' => $order->created_at->toISOString(),
            'updated_at' => $order->updated_at->toISOString(),
        ];
    }
}
