<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingModule;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrainingController extends Controller
{
    /**
     * List all published training modules grouped by network.
     */
    public function index(Request $request): JsonResponse
    {
        $modules = TrainingModule::where('is_published', true)
            ->with('network')
            ->orderBy('network_id')
            ->orderBy('title')
            ->get();

        $user = $request->user();
        $unlockedIds = [];
        $completedIds = [];
        if ($user) {
            $unlockedIds = DB::table('user_training_unlocks')
                ->where('user_id', $user->id)
                ->pluck('training_id')
                ->toArray();
            $completedIds = DB::table('user_training_completions')
                ->where('user_id', $user->id)
                ->pluck('training_id')
                ->toArray();
        }

        $grouped = $modules->groupBy('network_id')->map(function ($group) use ($unlockedIds, $completedIds) {
            $network = optional($group->first()->network);
            return [
                'network' => [
                    'id' => $network?->id,
                    'name' => $network?->name,
                    'logo_url' => $network?->logo_url,
                ],
                'modules' => $group->map(function ($m) use ($unlockedIds, $completedIds) {
                    return [
                        'id' => $m->id,
                        'title' => $m->title,
                        'category' => $m->category,
                        'preview_text' => $m->preview_text,
                        'thumbnail_url' => $m->thumbnail_url,
                        'credit_cost' => $m->credit_cost,
                        'estimated_time' => $m->estimated_time,
                        'difficulty' => $m->difficulty,
                        'is_unlocked' => in_array($m->id, $unlockedIds, true),
                        'is_completed' => in_array($m->id, $completedIds, true),
                    ];
                })->values(),
            ];
        })->values();

        $total = $modules->count();
        $completed = $user ? DB::table('user_training_completions')->where('user_id', $user->id)->count() : 0;
        $badges = $user ? DB::table('user_badges')->where('user_id', $user->id)->pluck('badge_code')->toArray() : [];

        return response()->json([
            'groups' => $grouped,
            'free_credits' => $user?->free_credits ?? 0,
            'paid_credits' => $user?->credits ?? 0,
            'progress' => [
                'completed' => $completed,
                'total' => $total,
            ],
            'badges' => $badges,
        ]);
    }

    /**
     * View a module if unlocked.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $module = TrainingModule::where('is_published', true)->findOrFail($id);
        $user = $request->user();

        $isUnlocked = DB::table('user_training_unlocks')
            ->where('user_id', $user->id)
            ->where('training_id', $module->id)
            ->exists();

        if (!$isUnlocked) {
            return response()->json(['message' => 'Training is locked'], 403);
        }

        return response()->json([
            'id' => $module->id,
            'title' => $module->title,
            'content' => $module->content,
            'estimated_time' => $module->estimated_time,
            'difficulty' => $module->difficulty,
            'network' => [
                'id' => $module->network?->id,
                'name' => $module->network?->name,
            ],
        ]);
    }

    /**
     * Unlock a training module by deducting free credits first.
     */
    public function unlock(Request $request, int $id, CreditService $creditService): JsonResponse
    {
        $user = $request->user();
        $module = TrainingModule::where('is_published', true)->findOrFail($id);

        $already = DB::table('user_training_unlocks')
            ->where('user_id', $user->id)
            ->where('training_id', $module->id)
            ->exists();
        if ($already) {
            return response()->json(['message' => 'Already unlocked'], 200);
        }

        if (!$creditService->hasEnoughTrainingCredits($user, (int) $module->credit_cost)) {
            return response()->json([
                'message' => 'Not enough credits. Purchase credits to unlock more trainings.',
                'free_credits' => $user->free_credits ?? 0,
                'paid_credits' => $user->credits ?? 0,
            ], 422);
        }

        DB::transaction(function () use ($user, $module, $creditService) {
            $creditService->deductTrainingCredits(
                $user,
                (int) $module->credit_cost,
                "Unlocked training: {$module->title}"
            );

            DB::table('user_training_unlocks')->insert([
                'user_id' => $user->id,
                'training_id' => $module->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $user->refresh();
        return response()->json([
            'message' => 'Training unlocked successfully',
            'free_credits' => $user->free_credits,
            'paid_credits' => $user->credits,
        ]);
    }

    /**
     * Unlock via body: POST /api/trainings/unlock { id }
     */
    public function unlockByBody(Request $request, CreditService $creditService): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:training_modules,id']
        ]);
        return $this->unlock($request, (int) $validated['id'], $creditService);
    }

    /**
     * Mark training as complete for the user.
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:training_modules,id']
        ]);

        $userId = $request->user()->id;
        $trainingId = (int) $validated['id'];

        // ensure unlocked before completion
        $isUnlocked = DB::table('user_training_unlocks')
            ->where('user_id', $userId)
            ->where('training_id', $trainingId)
            ->exists();
        if (!$isUnlocked) {
            return response()->json(['message' => 'Unlock this training before marking as complete'], 403);
        }

        DB::table('user_training_completions')->updateOrInsert(
            ['user_id' => $userId, 'training_id' => $trainingId],
            ['updated_at' => now(), 'created_at' => now()]
        );

        // Badge awarding
        $this->awardBadges($userId);

        $badges = DB::table('user_badges')->where('user_id', $userId)->pluck('badge_code');

        return response()->json([
            'message' => 'Marked as complete',
            'badges' => $badges,
        ]);
    }

    private function awardBadges(int $userId): void
    {
        $completedCount = (int) DB::table('user_training_completions')->where('user_id', $userId)->count();
        $total = (int) TrainingModule::where('is_published', true)->count();

        $award = function (string $code) use ($userId) {
            DB::table('user_badges')->updateOrInsert(
                ['user_id' => $userId, 'badge_code' => $code],
                ['updated_at' => now(), 'created_at' => now()]
            );
        };

        if ($completedCount >= 3) {
            $award('beginner');
        }
        if ($completedCount >= 5) {
            $award('intermediate');
        }
        if ($total > 0 && $completedCount >= $total) {
            $award('pro_marketer');
        }
        // 'affiliate_guru' reserved for future referral logic
    }
}


