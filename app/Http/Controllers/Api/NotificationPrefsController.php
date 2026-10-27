<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Quiet hours and muted alert kinds. Urgent alerts cannot be switched off. */
class NotificationPrefsController extends Controller
{
    private function present(Request $request): array
    {
        $p = NotificationPolicy::prefs($request->user());
        $needs = array_keys(NeedsController::PHRASES);

        return $p + [
            'urgent' => config('kindo.urgent_needs'),
            'mutable' => array_values(array_diff($needs, config('kindo.urgent_needs'))),
            'quiet_now' => NotificationPolicy::inQuietHours($p, now()),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->present($request));
    }

    public function update(Request $request): JsonResponse
    {
        $mutable = array_values(array_diff(array_keys(NeedsController::PHRASES), config('kindo.urgent_needs')));
        $data = $request->validate([
            'quiet_hours' => 'sometimes|array',
            'quiet_hours.enabled' => 'sometimes|boolean',
            'quiet_hours.start' => ['sometimes', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'quiet_hours.end' => ['sometimes', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'muted_needs' => 'sometimes|array',
            'muted_needs.*' => 'string|in:' . implode(',', $mutable),   // urgent kinds are refused
            'timezone' => 'sometimes|timezone:all',
        ]);

        $current = $request->user()->notification_prefs ?? [];
        if (isset($data['quiet_hours'])) {
            $data['quiet_hours'] = array_merge($current['quiet_hours'] ?? [], $data['quiet_hours']);
        }
        $request->user()->update(['notification_prefs' => array_merge($current, $data)]);

        return response()->json($this->present($request));
    }
}
