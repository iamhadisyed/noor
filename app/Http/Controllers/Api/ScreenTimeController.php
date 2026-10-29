<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ScreenTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Screen time rules, today's use and the week (Pro). The child's app reports use; a parent can give extra minutes. */
class ScreenTimeController extends Controller
{
    public function __construct(private ScreenTime $screen) {}

    private function pro(Request $request, $child): ?JsonResponse
    {
        if ($this->screen->proFor($child)) {
            return null;
        }

        return response()->json(['message' => 'Screen time limits and reports are part of Kindo Pro.', 'upgrade' => true], 402);
    }

    /** The day the device is on. The app sends it (a phone's own date); anything more than a day away from the server's is ignored. */
    private function day(Request $request): Carbon
    {
        $d = $request->query('date', $request->input('date'));
        try {
            $day = $d ? Carbon::parse($d)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            $day = now()->startOfDay();
        }

        return abs($day->diffInDays(now()->startOfDay())) <= 1 ? $day : now()->startOfDay();
    }

    public function show(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        if ($blocked = $this->pro($request, $child)) {
            return $blocked;
        }
        $day = $this->day($request);

        return response()->json([
            'settings' => $this->screen->settings($child),
            'today' => ['date' => $day->toDateString()] + $this->screen->state($child, $day),
            'week' => $this->screen->week($child, $day),
        ]);
    }

    public function update(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        if ($blocked = $this->pro($request, $child)) {
            return $blocked;
        }
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'daily_minutes' => 'sometimes|integer|between:10,600',
            'quiet_start' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'quiet_end' => ['sometimes', 'nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);
        $merged = array_merge($this->screen->settings($child), $data);
        if (($merged['quiet_start'] === null) !== ($merged['quiet_end'] === null)) {
            return response()->json(['message' => 'Set both the start and the end of quiet hours, or neither.', 'errors' => ['quiet_start' => ['Set both the start and the end of quiet hours, or neither.']]], 422);
        }
        $child->update(['screen_time' => $merged]);
        AuditLog::record($request->user(), 'child.screen_time', $child, array_keys($data));

        return $this->show($request, $childId);
    }

    /** The child's app tells us how long it was used. Idempotent per `Idempotency-Key` through the shared middleware. */
    public function usage(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $data = $request->validate(['seconds' => 'required|integer|between:1,3600', 'date' => 'nullable|date']);
        if (! $this->screen->proFor($child)) {
            return response()->json(['recorded' => false]);   // nothing to count on the free plan
        }
        $day = $this->day($request);
        $log = $this->screen->addUsage($child, $day, $data['seconds']);

        return response()->json(['recorded' => true] + $this->screen->state($child, $day));
    }

    public function extend(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        if ($blocked = $this->pro($request, $child)) {
            return $blocked;
        }
        $data = $request->validate(['minutes' => 'required|integer|between:5,120', 'date' => 'nullable|date']);
        $day = $this->day($request);
        $this->screen->log($child, $day)->increment('bonus_seconds', $data['minutes'] * 60);
        AuditLog::record($request->user(), 'child.screen_time_extended', $child, ['minutes' => $data['minutes']]);

        return response()->json(['today' => ['date' => $day->toDateString()] + $this->screen->state($child, $day)]);
    }
}
