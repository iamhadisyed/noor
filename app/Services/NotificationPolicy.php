<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Who should be buzzed, and when. Urgent alerts (potty, pain, help) always go through. Everything else can be
 * muted per kind and held back during the parent's quiet hours; it still appears in the app, it just does not buzz.
 */
class NotificationPolicy
{
    public const DEFAULTS = ['quiet_hours' => ['enabled' => false, 'start' => '22:00', 'end' => '07:00'], 'muted_needs' => [], 'timezone' => null];

    public static function prefs(User $user): array
    {
        $p = $user->notification_prefs ?? [];

        return [
            'quiet_hours' => array_merge(self::DEFAULTS['quiet_hours'], $p['quiet_hours'] ?? []),
            'muted_needs' => array_values($p['muted_needs'] ?? []),
            'timezone' => $p['timezone'] ?? config('app.timezone'),
        ];
    }

    public static function isUrgent(string $need): bool
    {
        return in_array(strtolower($need), config('kindo.urgent_needs'), true);
    }

    public static function shouldPush(User $user, string $need, ?CarbonInterface $now = null): bool
    {
        if (self::isUrgent($need)) {
            return true;
        }
        $p = self::prefs($user);
        if (in_array(strtolower($need), $p['muted_needs'], true)) {
            return false;
        }

        return ! self::inQuietHours($p, $now ?? now());
    }

    public static function inQuietHours(array $prefs, CarbonInterface $now): bool
    {
        $q = $prefs['quiet_hours'];
        if (! $q['enabled']) {
            return false;
        }
        $local = $now->copy()->setTimezone($prefs['timezone']);
        $minutes = $local->hour * 60 + $local->minute;
        [$sh, $sm] = array_map('intval', explode(':', $q['start']));
        [$eh, $em] = array_map('intval', explode(':', $q['end']));
        [$start, $end] = [$sh * 60 + $sm, $eh * 60 + $em];
        if ($start === $end) {
            return false;
        }

        // a window like 22:00 to 07:00 runs past midnight
        return $start < $end ? ($minutes >= $start && $minutes < $end) : ($minutes >= $start || $minutes < $end);
    }
}
