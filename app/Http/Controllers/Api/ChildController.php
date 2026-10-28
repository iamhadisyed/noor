<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Child;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildController extends Controller
{
    /** What a child gets when nothing has been chosen; a special track starts calmer and slower. */
    public const SUPPORT_STANDARD = ['magnet_px' => 24, 'pace' => 'normal', 'max_choices' => 4, 'no_timers' => false, 'first_then' => false, 'praise' => 'lively', 'guide' => 'dotted', 'fade_assist' => true];
    public const SUPPORT_SPECIAL = ['magnet_px' => 32, 'pace' => 'slow', 'max_choices' => 3, 'no_timers' => true, 'first_then' => true, 'praise' => 'quiet', 'guide' => 'dotted', 'fade_assist' => true];

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->accessibleChildren()->orderBy('id')->get()->map(fn ($c) => $this->present($c))->all());
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->present(self::owned($request, $id)));
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
            'date_of_birth' => 'required|date|before:today|after:-25 years',
            'neurotype' => 'sometimes|in:neurotypical,autism_spectrum,speech_delay,adhd,down_syndrome',
            'avatar_id' => 'sometimes|string|max:50',
        ] + $this->supportRules());

        // A child with a diagnosis starts on the special track unless the parent says otherwise.
        $data['track'] ??= ($data['neurotype'] ?? 'neurotypical') === 'neurotypical' ? 'standard' : 'special';
        $data['sensory_mode'] ??= $data['track'] === 'special';

        return response()->json($this->present($user->children()->create($data)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $child = self::owned($request, $id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'date_of_birth' => 'sometimes|date|before:today|after:-25 years',
            'neurotype' => 'sometimes|in:neurotypical,autism_spectrum,speech_delay,adhd,down_syndrome',
            'avatar_id' => 'sometimes|string|max:50',
        ] + $this->supportRules());

        if (isset($data['support_profile'])) {
            $data['support_profile'] = array_merge($child->support_profile ?? [], $data['support_profile']);
        }
        $child->update($data);

        return response()->json($this->present($child->fresh()));
    }

    /** Privacy: removes the child and everything attached, including every stored picture and sound. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $child = self::owned($request, $id);
        if ($child->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the parent who added this child can delete their profile.'], 403);
        }

        AuditLog::record($request->user(), 'child.deleted', $child, ['name_initial' => mb_substr($child->name, 0, 1)]);
        app(\App\Services\AccountEraser::class)->eraseChild($child);

        return response()->json(['ok' => true]);
    }

    /** Resolve a child owned by the authenticated parent or 404. */
    public static function owned(Request $request, int $childId): Child
    {
        return $request->user()->accessibleChildren()->findOrFail($childId);
    }

    public function present(Child $c): array
    {
        $defaults = $c->track === 'special' ? self::SUPPORT_SPECIAL : self::SUPPORT_STANDARD;

        return $c->toArray() + ['support' => array_merge($defaults, $c->support_profile ?? []), 'rewards' => app(\App\Services\RewardService::class)->summary($c)];
    }

    private function supportRules(): array
    {
        return [
            'track' => 'sometimes|in:standard,special',
            'sensory_mode' => 'sometimes|boolean',
            'support_profile' => 'sometimes|array',
            'support_profile.magnet_px' => 'sometimes|integer|between:15,40',
            'support_profile.pace' => 'sometimes|in:slow,normal',
            'support_profile.max_choices' => 'sometimes|integer|between:2,4',
            'support_profile.no_timers' => 'sometimes|boolean',
            'support_profile.first_then' => 'sometimes|boolean',
            'support_profile.praise' => 'sometimes|in:quiet,lively',
            'support_profile.guide' => 'sometimes|in:none,dotted,arrows',
            'support_profile.fade_assist' => 'sometimes|boolean',
        ];
    }
}
