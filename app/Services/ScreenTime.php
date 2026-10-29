<?php

namespace App\Services;

use App\Models\Child;
use App\Models\ScreenTimeLog;
use Illuminate\Support\Carbon;

/**
 * Screen time for a child: a daily limit and quiet hours (a bedtime, school time). A Pro feature, enforced by the child app, which keeps
 * counting offline. The server keeps the rules, the days' totals for the parent's report, and the extra minutes a parent gives.
 */
class ScreenTime
{
    public const DEFAULTS = ['enabled' => false, 'daily_minutes' => 60, 'quiet_start' => null, 'quiet_end' => null];

    public function settings(Child $child): array
    {
        return array_merge(self::DEFAULTS, $child->screen_time ?? []);
    }

    /** Is this child's household on Pro? The rules only count while it is. */
    public function proFor(Child $child): bool
    {
        return (bool) $child->user?->hasProAccess();
    }

    /** What the child's app should enforce: the rules, or null when switched off or the plan has ended. */
    public function effective(Child $child): ?array
    {
        $s = $this->settings($child);

        return $s['enabled'] && $this->proFor($child) ? $s : null;
    }

    public function log(Child $child, Carbon|string $day): ScreenTimeLog
    {
        return ScreenTimeLog::firstOrCreate(['child_id' => $child->id, 'day' => Carbon::parse($day)->toDateString()], ['seconds' => 0, 'bonus_seconds' => 0]);
    }

    public function addUsage(Child $child, Carbon|string $day, int $seconds): ScreenTimeLog
    {
        $log = $this->log($child, $day);
        $log->increment('seconds', max(0, min(3600, $seconds)));   // one report never adds more than an hour

        return $log->refresh();
    }

    /** Today's picture: used, extra minutes given, what is left. `reason` is why the screen is locked (`limit`), or null. */
    public function state(Child $child, Carbon|string $day): array
    {
        $s = $this->settings($child);
        $log = ScreenTimeLog::where('child_id', $child->id)->where('day', Carbon::parse($day)->toDateString())->first();
        $used = (int) ($log->seconds ?? 0);
        $bonus = (int) ($log->bonus_seconds ?? 0);
        $allowed = $s['daily_minutes'] * 60 + $bonus;
        $left = max(0, $allowed - $used);

        return ['used_seconds' => $used, 'bonus_seconds' => $bonus, 'allowed_seconds' => $allowed, 'remaining_seconds' => $left,
            'locked' => (bool) $s['enabled'] && $left === 0, 'reason' => $s['enabled'] && $left === 0 ? 'limit' : null];
    }

    /** The last [days] days, oldest first, with zeros for days nothing was used. */
    public function week(Child $child, Carbon|string $today, int $days = 7): array
    {
        $end = Carbon::parse($today)->startOfDay();
        $rows = ScreenTimeLog::where('child_id', $child->id)->whereBetween('day', [$end->copy()->subDays($days - 1)->toDateString(), $end->toDateString()])->get()->keyBy(fn ($r) => $r->day->toDateString());
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = $end->copy()->subDays($i)->toDateString();
            $out[] = ['date' => $d, 'seconds' => (int) ($rows[$d]->seconds ?? 0)];
        }

        return $out;
    }
}
