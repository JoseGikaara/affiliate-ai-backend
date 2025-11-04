<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GenerateController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'affiliate_link' => ['required', 'string', 'max:500'],
            'affiliate_network' => ['nullable', 'string', 'max:255'],
            'niche' => ['nullable', 'string', 'max:200'],
            'extra_context' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $apiKey = config('services.openai.api_key');
        if (!$apiKey) {
            return response()->json([
                'message' => 'OpenAI API key not configured.',
            ], 500);
        }

        $costPerTask = (int) config('credits.cost_per_task', 5);
        if (($user->credits ?? 0) < $costPerTask) {
            return response()->json([
                'message' => 'Insufficient credits. Please buy more credits to continue.',
            ], 402);
        }

        $prompt = $this->buildPrompt($validated['affiliate_link'], $validated['extra_context'] ?? null);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an expert affiliate marketing assistant. Return concise, actionable plans.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
            ]);

        if ($response->failed()) {
            return response()->json([
                'message' => 'AI provider request failed.',
                'details' => $response->json(),
            ], 502);
        }

        $ai = $response->json();
        $content = $ai['choices'][0]['message']['content'] ?? '';

        $project = DB::transaction(function () use ($user, $costPerTask, $validated, $content) {
            $user->decrement('credits', $costPerTask);
            CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => -$costPerTask,
                'type' => 'deduction',
                'description' => 'AI generation request',
            ]);

            // Auto-save as project
            $project = Project::create([
                'user_id' => $user->id,
                'name' => 'AI Generated Plan - ' . now()->format('Y-m-d H:i'),
                'description' => $content,
                'affiliate_link' => $validated['affiliate_link'],
                'status' => 'active',
            ]);

            return $project;
        });

        return response()->json([
            'content' => $content,
            'remaining_credits' => $user->fresh()->credits,
            'project' => $project,
        ]);
    }

    private function buildPrompt(string $affiliateLink, ?string $extraContext): string
    {
        $parts = [
            "Affiliate link: {$affiliateLink}",
            'Task: Provide a marketing strategy, 2-week content plan, and 3 landing page ideas optimized for conversions. Include channels, hooks, and CTAs.',
        ];
        if (!empty($extraContext)) {
            $parts[] = "Extra context: {$extraContext}";
        }
        $parts[] = 'Output format: 1) Strategy, 2) Content Plan (bullets), 3) Landing Page Ideas (bullets).';
        return implode("\n\n", $parts);
    }
}
