<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateNetwork;
use App\Models\TrainingModule;
use App\Services\TrainingContentGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTrainingModuleController extends Controller
{
    public function index(): JsonResponse
    {
        $modules = TrainingModule::with('network:id,name')
            ->latest()
            ->get();

        return response()->json($modules);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'network_id' => ['required', 'integer', 'exists:affiliate_networks,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:500'],
            'thumbnail_url' => ['nullable', 'url'],
            'estimated_time' => ['nullable', 'string', 'max:50'],
            'difficulty' => ['nullable', 'string', 'in:Beginner,Intermediate,Advanced'],
            'credit_cost' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['boolean'],
        ]);

        $module = TrainingModule::create([
            'network_id' => $validated['network_id'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'category' => $validated['category'] ?? null,
            'preview_text' => $validated['preview_text'] ?? null,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'estimated_time' => $validated['estimated_time'] ?? null,
            'difficulty' => $validated['difficulty'] ?? null,
            'credit_cost' => $validated['credit_cost'] ?? 5,
            'is_published' => (bool) ($validated['is_published'] ?? false),
        ]);

        return response()->json(['message' => 'Training module created', 'module' => $module], 201);
    }

    public function update(Request $request, TrainingModule $module): JsonResponse
    {
        $validated = $request->validate([
            'network_id' => ['nullable', 'integer', 'exists:affiliate_networks,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:500'],
            'thumbnail_url' => ['nullable', 'url'],
            'estimated_time' => ['nullable', 'string', 'max:50'],
            'difficulty' => ['nullable', 'string', 'in:Beginner,Intermediate,Advanced'],
            'credit_cost' => ['nullable', 'integer', 'min:1'],
            'is_published' => ['nullable', 'boolean'],
        ]);

        $module->update($validated);
        return response()->json(['message' => 'Training module updated', 'module' => $module->fresh()]);
    }

    public function destroy(TrainingModule $module): JsonResponse
    {
        $module->delete();
        return response()->json(['message' => 'Training module deleted']);
    }

    /**
     * Generate or regenerate training content for a network using AI
     */
    public function generate(int $networkId, TrainingContentGenerator $generator): JsonResponse
    {
        $network = AffiliateNetwork::where('is_active', true)->findOrFail($networkId);

        try {
            $content = $generator->generateTraining(
                $network->name,
                $network->category,
                $network->description
            );

            $title = $generator->generateTitle($network->name);

            // Check if training already exists
            $training = TrainingModule::where('network_id', $network->id)->first();

            if ($training) {
                $training->update([
                    'title' => $title,
                    'content' => $content,
                    'credit_cost' => 5,
                    'is_published' => true,
                ]);
            } else {
                $training = TrainingModule::create([
                    'network_id' => $network->id,
                    'title' => $title,
                    'content' => $content,
                    'credit_cost' => 5,
                    'is_published' => true,
                ]);
            }

            return response()->json([
                'message' => 'Training content generated successfully',
                'module' => $training->fresh()->load('network:id,name'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate training content: ' . $e->getMessage(),
            ], 500);
        }
    }
}


