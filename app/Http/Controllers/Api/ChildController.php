<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->children()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = $user->isPro() ? config('kindo.free_limits.pro_children') : config('kindo.free_limits.children');

        if ($user->children()->count() >= $limit) {
            return response()->json(['message' => 'Child profile limit reached for your plan.', 'upgrade' => ! $user->isPro()], 402);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'neurotype' => 'sometimes|in:neurotypical,autism_spectrum,speech_delay,adhd,down_syndrome',
            'sensory_mode' => 'sometimes|boolean',
            'avatar_id' => 'sometimes|string|max:50',
        ]);

        return response()->json($user->children()->create($data), 201);
    }

    /** Resolve a child owned by the authenticated parent or 404. */
    public static function owned(Request $request, int $childId): Child
    {
        return $request->user()->children()->findOrFail($childId);
    }
}
