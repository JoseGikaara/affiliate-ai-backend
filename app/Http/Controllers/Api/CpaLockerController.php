<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AffiliateUnlock;
use App\Models\CpaLocker;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class CpaLockerController extends Controller
{
    /**
     * List all active CPA lockers
     */
    public function index(Request $request): JsonResponse
    {
        $lockers = CpaLocker::where('is_active', true)
            ->with(['creator:id,name,email'])
            ->latest()
            ->get()
            ->map(function ($locker) use ($request) {
                $isUnlocked = false;
                if ($request->user()) {
                    $isUnlocked = AffiliateUnlock::where('user_id', $request->user()->id)
                        ->where('locker_id', $locker->id)
                        ->exists();
                }

                return [
                    'id' => $locker->id,
                    'title' => $locker->title,
                    'description' => $locker->description,
                    'cost' => $locker->cost,
                    'file_url' => $locker->file_url,
                    'image' => $locker->image ? Storage::disk('public')->url($locker->image) : null,
                    'is_unlocked' => $isUnlocked,
                    'created_by' => [
                        'id' => $locker->creator->id,
                        'name' => $locker->creator->name,
                    ],
                    'created_at' => $locker->created_at->toISOString(),
                ];
            });

        return response()->json($lockers);
    }

    /**
     * Admin: Create a new CPA locker
     */
    public function store(Request $request, CreditService $creditService): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'cost' => ['required', 'integer', 'min:1'],
            'file_url' => ['nullable', 'url'],
            'file' => ['nullable', 'file', 'max:10240'], // 10MB max
            'image' => ['nullable', 'image', 'max:2048'], // 2MB max
        ]);

        $locker = DB::transaction(function () use ($request, $validated) {
            $lockerData = [
                'title' => $validated['title'],
                'description' => $validated['description'],
                'cost' => (int) $validated['cost'],
                'created_by' => $request->user()->id,
                'is_active' => true,
            ];

            // Handle file upload
            if ($request->hasFile('file')) {
                $filePath = $request->file('file')->store('cpa_lockers', 'public');
                $lockerData['file_url'] = Storage::disk('public')->url($filePath);
            } elseif (isset($validated['file_url'])) {
                $lockerData['file_url'] = $validated['file_url'];
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('cpa_lockers/images', 'public');
                $lockerData['image'] = $imagePath;
            }

            return CpaLocker::create($lockerData);
        });

        return response()->json([
            'message' => 'CPA locker created successfully',
            'locker' => $locker->load('creator'),
        ], 201);
    }

    /**
     * Admin: Update a CPA locker
     */
    public function update(Request $request, CpaLocker $locker): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'cost' => ['sometimes', 'integer', 'min:1'],
            'file_url' => ['nullable', 'url'],
            'file' => ['nullable', 'file', 'max:10240'],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $locker, $validated) {
            // Handle file upload
            if ($request->hasFile('file')) {
                // Delete old file if exists
                if ($locker->file_url && strpos($locker->file_url, '/storage/') !== false) {
                    $oldFilePath = str_replace('/storage/', '', $locker->file_url);
                    Storage::disk('public')->delete($oldFilePath);
                }
                $filePath = $request->file('file')->store('cpa_lockers', 'public');
                $validated['file_url'] = Storage::disk('public')->url($filePath);
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($locker->image) {
                    Storage::disk('public')->delete($locker->image);
                }
                $imagePath = $request->file('image')->store('cpa_lockers/images', 'public');
                $validated['image'] = $imagePath;
            }

            $locker->update($validated);
        });

        return response()->json([
            'message' => 'CPA locker updated successfully',
            'locker' => $locker->fresh()->load('creator'),
        ]);
    }

    /**
     * Admin: Delete a CPA locker
     */
    public function destroy(Request $request, CpaLocker $locker): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        DB::transaction(function () use ($locker) {
            // Delete associated file if exists
            if ($locker->file_url && strpos($locker->file_url, '/storage/') !== false) {
                $filePath = str_replace('/storage/', '', $locker->file_url);
                Storage::disk('public')->delete($filePath);
            }

            // Delete associated image if exists
            if ($locker->image) {
                Storage::disk('public')->delete($locker->image);
            }

            $locker->delete();
        });

        return response()->json(['message' => 'CPA locker deleted successfully']);
    }

    /**
     * Unlock a CPA locker (deduct credits and record unlock)
     */
    public function unlock(Request $request, CpaLocker $locker, CreditService $creditService): JsonResponse
    {
        $user = $request->user();

        // Check if already unlocked
        $existingUnlock = AffiliateUnlock::where('user_id', $user->id)
            ->where('locker_id', $locker->id)
            ->first();

        if ($existingUnlock) {
            return response()->json([
                'message' => 'Already unlocked',
                'unlock' => $existingUnlock,
                'file_url' => $locker->file_url,
            ]);
        }

        // Check if user has enough credits
        if (!$creditService->hasEnoughCredits($user, $locker->cost)) {
            return response()->json([
                'message' => 'Not enough credits',
                'required' => $locker->cost,
                'available' => $user->credits ?? 0,
            ], 422);
        }

        // Deduct credits and record unlock
        DB::transaction(function () use ($user, $locker, $creditService) {
            $creditService->deductCredits(
                $user,
                $locker->cost,
                "Unlocked CPA locker: {$locker->title}"
            );

            AffiliateUnlock::create([
                'user_id' => $user->id,
                'locker_id' => $locker->id,
                'credits_spent' => $locker->cost,
            ]);
        });

        return response()->json([
            'message' => 'CPA locker unlocked successfully',
            'file_url' => $locker->file_url,
            'locker' => [
                'id' => $locker->id,
                'title' => $locker->title,
            ],
            'remaining_credits' => $user->fresh()->credits,
        ]);
    }

    /**
     * Download or access unlocked file/link
     */
    public function download(Request $request, CpaLocker $locker): JsonResponse
    {
        $user = $request->user();

        // Check if user has unlocked this locker
        $unlock = AffiliateUnlock::where('user_id', $user->id)
            ->where('locker_id', $locker->id)
            ->first();

        if (!$unlock) {
            return response()->json(['message' => 'You must unlock this offer first'], 403);
        }

        // If it's a file (stored locally), return download link
        if ($locker->file_url && strpos($locker->file_url, '/storage/') !== false) {
            $filePath = str_replace('/storage/', '', $locker->file_url);
            if (Storage::disk('public')->exists($filePath)) {
                return response()->json([
                    'download_url' => $locker->file_url,
                    'type' => 'file',
                ]);
            }
        }

        // If it's an external URL, return redirect
        if ($locker->file_url) {
            return response()->json([
                'redirect_url' => $locker->file_url,
                'type' => 'link',
            ]);
        }

        return response()->json(['message' => 'No file or link available'], 404);
    }

    /**
     * Admin: Get all lockers (including inactive)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user()->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $lockers = CpaLocker::with(['creator:id,name,email'])
            ->withCount('unlocks')
            ->latest()
            ->get()
            ->map(function ($locker) {
                return [
                    'id' => $locker->id,
                    'title' => $locker->title,
                    'description' => $locker->description,
                    'cost' => $locker->cost,
                    'file_url' => $locker->file_url,
                    'image' => $locker->image ? Storage::disk('public')->url($locker->image) : null,
                    'is_active' => $locker->is_active,
                    'unlocks_count' => $locker->unlocks_count,
                    'created_by' => [
                        'id' => $locker->creator->id,
                        'name' => $locker->creator->name,
                    ],
                    'created_at' => $locker->created_at->toISOString(),
                ];
            });

        return response()->json($lockers);
    }
}

