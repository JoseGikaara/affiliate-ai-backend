<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($projects);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($project);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'affiliate_network' => ['nullable', 'string', 'max:255'],
            'affiliate_link' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:active,completed,archived'],
        ]);

        $project = Project::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json($project, 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:active,completed,archived'],
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $project->delete();

        return response()->json(['message' => 'Project deleted']);
    }
}

