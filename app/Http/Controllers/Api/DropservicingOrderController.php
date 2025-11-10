<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDropservicingOrderJob;
use App\Models\DropservicingOrder;
use App\Models\DropservicingService;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DropservicingOrderController extends Controller
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * Display a listing of user's orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = DropservicingOrder::with(['service', 'fulfillmentLog'])
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function ($order) {
                return $this->formatOrder($order);
            });

        return response()->json($orders);
    }

    /**
     * Store a newly created order
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'exists:dropservicing_services,id'],
            'input_data' => ['required', 'array'],
        ]);

        $user = $request->user();
        $service = DropservicingService::findOrFail($validated['service_id']);

        // Check if service is active
        if (!$service->isActive()) {
            return response()->json([
                'message' => 'This service is currently unavailable.',
            ], 422);
        }

        // Check if user has enough credits
        if (!$this->creditService->hasEnoughCredits($user, $service->credit_cost)) {
            return response()->json([
                'message' => 'Insufficient credits. You need ' . $service->credit_cost . ' credits to order this service.',
                'required_credits' => $service->credit_cost,
                'available_credits' => $user->credits,
            ], 402);
        }

        // Validate input_data against service input_fields if defined
        if ($service->input_fields) {
            $requiredFields = collect($service->input_fields)
                ->where('required', true)
                ->pluck('name')
                ->toArray();

            $providedFields = array_keys($validated['input_data']);

            $missingFields = array_diff($requiredFields, $providedFields);
            if (!empty($missingFields)) {
                return response()->json([
                    'message' => 'Missing required fields: ' . implode(', ', $missingFields),
                    'missing_fields' => $missingFields,
                ], 422);
            }
        }

        // Deduct credits immediately
        $this->creditService->deductCredits($user, $service->credit_cost, 'Dropservicing order: ' . $service->name);

        // Create order
        $order = DropservicingOrder::create([
            'user_id' => $user->id,
            'service_id' => $service->id,
            'input_data' => $validated['input_data'],
            'status' => 'pending',
            'credits_used' => $service->credit_cost,
        ]);

        // Dispatch AI fulfillment job
        ProcessDropservicingOrderJob::dispatch($order->id);

        return response()->json([
            'message' => 'Order created successfully. Processing...',
            'order' => $this->formatOrder($order->fresh(['service'])),
        ], 201);
    }

    /**
     * Display the specified order
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $order = DropservicingOrder::with(['service', 'fulfillmentLog'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json($this->formatOrder($order));
    }

    /**
     * Format order for API response
     */
    protected function formatOrder(DropservicingOrder $order): array
    {
        return [
            'id' => $order->id,
            'service' => [
                'id' => $order->service->id,
                'name' => $order->service->name,
                'description' => $order->service->description,
            ],
            'input_data' => $order->input_data,
            'ai_response' => $order->ai_response,
            'status' => $order->status,
            'credits_used' => $order->credits_used,
            'fulfillment_log' => $order->fulfillmentLog ? [
                'ai_model' => $order->fulfillmentLog->ai_model,
                'tokens_used' => $order->fulfillmentLog->tokens_used,
                'success' => $order->fulfillmentLog->success,
            ] : null,
            'created_at' => $order->created_at->toISOString(),
            'updated_at' => $order->updated_at->toISOString(),
        ];
    }
}
