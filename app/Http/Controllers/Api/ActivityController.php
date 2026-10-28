<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalmSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Calm and movement activities: no score, no play minutes, never locked. Defined in config/activities.php. */
class ActivityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['activities' => collect(config('activities'))->map(fn ($a, $k) => ['activity' => $k] + $a)->values()->all()]);
    }

    /** The app tells us a child finished one. Only used for the parent's "what helps" report. */
    public function done(Request $request, int $childId, string $activity): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        abort_unless(array_key_exists($activity, config('activities')), 404);
        $data = $request->validate(['seconds' => 'sometimes|integer|min:0|max:3600']);

        $s = CalmSession::create(['child_id' => $child->id, 'activity' => $activity, 'seconds' => $data['seconds'] ?? 0]);

        return response()->json(['id' => $s->id], 201);
    }

    /** Counts for the parent report. */
    public static function summary(int $childId, $from, $to): array
    {
        $rows = CalmSession::where('child_id', $childId)->whereBetween('created_at', [$from, $to])->get();

        return ['sessions' => $rows->count(), 'minutes' => (int) round($rows->sum('seconds') / 60), 'by_activity' => $rows->groupBy('activity')->map->count()->sortDesc()];
    }
}
