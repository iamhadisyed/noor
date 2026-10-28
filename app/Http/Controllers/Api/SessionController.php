<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    /** Offline-first sync: the child app uploads a finished practice session with its mistakes. */
    public function store(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        $data = $request->validate([
            'module_id' => 'nullable|integer|exists:syllabus_modules,id',
            'language' => 'required|in:urdu,english',
            'total_attempts' => 'required|integer|min:0|max:5000',
            'successful_attempts' => 'required|integer|min:0|lte:total_attempts',
            'duration_seconds' => 'required|integer|min:0|max:43200',   // twelve hours is already more than a child can practise
            'mistakes' => 'sometimes|array|max:200',
            'mistakes.*.target_string' => 'required|string|max:100',
            'mistakes.*.attempted_string' => 'required|string|max:100',
            'mistakes.*.stroke_deviation_score' => 'nullable|numeric|between:0,999.99',
            'mistakes.*.mistake_type' => 'required|in:phonetic_substitution,letter_omission,dot_misplacement,stroke_inversion',
            // Optional per-letter tracing results; older app versions simply leave this out.
            'letters' => 'sometimes|array|max:120',
            'letters.*.letter' => 'required|string|max:20',
            'letters.*.attempts' => 'required|integer|between:1,200',
            'letters.*.successes' => 'required|integer|min:0|lte:letters.*.attempts',
            'letters.*.assisted' => 'sometimes|integer|min:0|lte:letters.*.attempts',
            'letters.*.deviation' => 'nullable|numeric|between:0,999.99',
        ]);

        $session = DB::transaction(function () use ($child, $data) {
            $session = $child->practiceSessions()->create(collect($data)->except('mistakes', 'letters')->all());
            $session->mistakes()->createMany($data['mistakes'] ?? []);
            $session->letters()->createMany(collect($data['letters'] ?? [])->map(fn ($l) => $l + ['child_id' => $child->id, 'language' => $data['language']])->all());
            return $session;
        });

        return response()->json(['id' => $session->id, 'reward' => app(\App\Services\RewardService::class)->forSession($child, $session)], 201);
    }
}
