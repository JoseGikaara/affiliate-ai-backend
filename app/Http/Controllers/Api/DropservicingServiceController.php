<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DropservicingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DropservicingServiceController extends Controller
{
    /**
     * Display a listing of active services (public endpoint for users)
     */
    public function index(Request $request): JsonResponse
    {
        $services = DropservicingService::where('status', 'active')
            ->latest()
            ->get()
            ->map(function ($service) {
                return $this->formatService($service);
            });

        return response()->json($services);
    }

    /**
     * Store a newly created resource in storage (admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'credit_cost' => ['required', 'integer', 'min:1'],
            'ai_prompt_template' => ['required', 'string'],
            'delivery_time' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'input_fields' => ['nullable', 'array'],
        ]);

        $service = DropservicingService::create($validated);

        return response()->json([
            'message' => 'Service created successfully',
            'service' => $this->formatService($service),
        ], 201);
    }

    /**
     * Display the specified resource
     */
    public function show(string $id): JsonResponse
    {
        $service = DropservicingService::findOrFail($id);

        return response()->json($this->formatService($service));
    }

    /**
     * Update the specified resource in storage (admin only)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $service = DropservicingService::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'credit_cost' => ['sometimes', 'integer', 'min:1'],
            'ai_prompt_template' => ['sometimes', 'string'],
            'delivery_time' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'input_fields' => ['nullable', 'array'],
        ]);

        $service->update($validated);

        return response()->json([
            'message' => 'Service updated successfully',
            'service' => $this->formatService($service->fresh()),
        ]);
    }

    /**
     * Remove the specified resource from storage (admin only)
     */
    public function destroy(string $id): JsonResponse
    {
        $service = DropservicingService::findOrFail($id);
        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully',
        ]);
    }

    /**
     * Format service for API response
     */
    protected function formatService(DropservicingService $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'credit_cost' => $service->credit_cost,
            'delivery_time' => $service->delivery_time,
            'status' => $service->status,
            'input_fields' => $service->input_fields ?? [],
            'created_at' => $service->created_at->toISOString(),
            'updated_at' => $service->updated_at->toISOString(),
        ];
    }
}
