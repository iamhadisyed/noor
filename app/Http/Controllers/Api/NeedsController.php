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

        $label = ucfirst($data['need_type']);
        $fcm->sendToUser($child->user_id, "{$child->name} needs: {$label}", 'Tap to acknowledge.', [
            'type' => 'need_alert', 'log_id' => $log->id, 'child_id' => $child->id, 'need_type' => $data['need_type'],
        ]);

        return response()->json(['id' => $log->id], 200);
    }

    public function feed(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        return response()->json($child->needsLogs()->latest('created_at')->limit(50)->get());
    }

    public function acknowledge(Request $request, int $logId): JsonResponse
    {
        $log = ChildNeedsLog::whereIn('child_id', $request->user()->children()->select('id'))->findOrFail($logId);
        $log->update(['acknowledged' => true, 'acknowledged_at' => now()]);

        return response()->json($log);
    }

    public function cards(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = AacCard::where(fn ($q) => $q->whereNull('child_id')->orWhereIn('child_id', $user->children()->select('id')));
        if (! $user->isPro()) {
            $query->where('is_pro', false);
        }

        return response()->json($query->orderBy('category')->get());
    }
}
