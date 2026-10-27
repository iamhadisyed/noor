<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AacCard;
use App\Models\ChildNeedsLog;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeedsController extends Controller
{
    /** How the push reads: "Ayan needs the potty", "Ayan is hungry". */
    public const PHRASES = [
        'potty' => 'needs the potty', 'water' => 'wants water', 'food' => 'is hungry', 'sleep' => 'is sleepy',
        'hug' => 'needs a hug', 'pain' => 'is in pain', 'help' => 'needs help',
    ];

    /** Child app one-tap alert: log it, then push to every parent device. */
    public function alert(Request $request, FcmService $fcm): JsonResponse
    {
        $data = $request->validate([
            'child_id' => 'required|integer',
            'need_type' => 'required|string|max:50',
            'card_id' => 'nullable|integer|exists:aac_cards,id',
        ]);

        $child = ChildController::owned($request, $data['child_id']);

        $log = ChildNeedsLog::create([
            'child_id' => $child->id,
            'card_id' => $data['card_id'] ?? null,
            'need_type' => $data['need_type'],
        ]);

        $phrase = self::PHRASES[strtolower($data['need_type'])] ?? 'needs ' . strtolower($data['need_type']);
        // Every parent in the household hears about it, and either can reply.
        foreach (\App\Services\Household::userIdsFor($child) as $parentId) {
            $fcm->sendToUser($parentId, "{$child->name} {$phrase}", 'Tap to respond.', [
                'type' => 'need_alert', 'log_id' => $log->id, 'child_id' => $child->id, 'need_type' => $data['need_type'],
                'actions' => 'ack,call',
            ]);
        }

        return response()->json(['id' => $log->id], 200);
    }

    public function feed(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        return response()->json($child->needsLogs()->with('responder:id,name')->latest('created_at')->limit(50)->get()->map(fn ($l) => $this->present($l))->all());
    }

    /** Status of one alert, for the child app ("Mama is on the way") and the parent detail screen. */
    public function show(Request $request, int $logId): JsonResponse
    {
        return response()->json($this->present($this->ownedLog($request, $logId)->load('responder:id,name')));
    }

    /** The parent opened the alert. Idempotent. */
    public function seen(Request $request, int $logId): JsonResponse
    {
        $log = $this->ownedLog($request, $logId);
        $log->seen_at ??= now();
        $log->save();

        return response()->json($this->present($log->load('responder:id,name')));
    }

    public function acknowledge(Request $request, int $logId): JsonResponse
    {
        $log = $this->ownedLog($request, $logId);
        if (! $log->acknowledged) {
            $log->update(['acknowledged' => true, 'acknowledged_at' => now(), 'acknowledged_by' => $request->user()->id, 'seen_at' => $log->seen_at ?? now()]);
        }

        return response()->json($this->present($log->load('responder:id,name')));
    }

    public function cards(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AacCard::where(fn ($q) => $q->whereNull('child_id')->orWhereIn('child_id', $user->accessibleChildren()->select('id')));
        if (! $user->isPro()) {
            $query->where('is_pro', false);
        }

        return response()->json($query->orderBy('category')->get());
    }

    private function ownedLog(Request $request, int $id): ChildNeedsLog
    {
        return ChildNeedsLog::whereIn('child_id', $request->user()->accessibleChildren()->select('id'))->findOrFail($id);
    }

    public function present(ChildNeedsLog $l): array
    {
        $status = $l->acknowledged ? 'on_the_way' : ($l->seen_at ? 'seen' : 'sent');

        return [
            'id' => $l->id, 'child_id' => $l->child_id, 'need_type' => $l->need_type, 'status' => $status,
            'created_at' => $l->created_at, 'seen_at' => $l->seen_at, 'acknowledged_at' => $l->acknowledged_at,
            'replied_by' => $l->responder?->name,
            'reply_seconds' => $l->acknowledged_at ? (int) $l->created_at->diffInSeconds($l->acknowledged_at) : null,
        ];
    }
}
