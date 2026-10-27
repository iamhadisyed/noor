<?php

namespace App\Services;

use App\Models\Child;
use App\Models\PracticeSession;
use App\Models\RewardLedger;
use App\Models\User;

/**
 * Practice earns play minutes; games spend them. Every rule here is a setting the parent can change per child.
 * Earning is tied to a practice session id, so a retried upload can never pay twice.
 */
class RewardService
{
    private const EARN = ['good_score', 'effort', 'improvement', 'streak'];

    public function settings(Child $child): array
    {
        $base = config('games.rewards');
        if ($child->track === 'special') {
            $base = array_merge($base, config('games.rewards_special'));
        }

        return array_merge($base, array_filter($child->reward_settings ?? [], fn ($v) => $v !== null));
    }

    public function balance(Child $child): int
    {
        return max(0, (int) $child->rewardLedger()->sum('minutes'));
    }

    public function earnedToday(Child $child): int
    {
        return (int) $child->rewardLedger()->whereIn('reason', self::EARN)->where('created_at', '>=', now()->startOfDay())->sum('minutes');
    }

    public function summary(Child $child): array
    {
        $s = $this->settings($child);

        return ['enabled' => (bool) $s['enabled'], 'balance_minutes' => $this->balance($child), 'earned_today' => $this->earnedToday($child), 'daily_cap_minutes' => (int) $s['daily_cap_minutes']];
    }

    /** What a finished practice session earns. Returns the minutes added and why. */
    public function forSession(Child $child, PracticeSession $session): array
    {
        $s = $this->settings($child);
        $none = ['earned' => 0, 'reasons' => [], 'balance_minutes' => $this->balance($child), 'capped' => false];
        if (! $s['enabled'] || $session->total_attempts < (int) $s['min_attempts']) {
            return $none;
        }

        $accuracy = $session->total_attempts ? $session->successful_attempts / $session->total_attempts * 100 : 0;
        $wanted = [];

        if ($accuracy >= $s['threshold_pct']) {
            $wanted['good_score'] = (int) $s['minutes_per_good_session'];
        } elseif ($s['reward_for_effort']) {
            $wanted['effort'] = (int) $s['effort_minutes'];
        }

        // Better than your own recent average: this is for the child who is climbing, not only the one who is already top.
        $recent = $child->practiceSessions()->where('id', '<', $session->id)->where('total_attempts', '>=', (int) $s['min_attempts'])->where('created_at', '>=', now()->subDays(14))->orderByDesc('id')->limit(5)->get();
        if ($recent->count() >= 3) {
            $avg = $recent->avg(fn ($r) => $r->successful_attempts / $r->total_attempts * 100);
            if ($accuracy > 0 && $accuracy >= $avg + $s['improvement_points']) {
                $wanted['improvement'] = (int) $s['improvement_minutes'];
            }
        }

        // Practising several days in a row; paid once per day.
        $today = (int) now()->format('Ymd');
        if (! $child->rewardLedger()->where('reason', 'streak')->where('ref_id', $today)->exists() && $this->streakDays($child) >= (int) $s['streak_days']) {
            $wanted['streak'] = (int) $s['streak_minutes'];
        }

        $room = max(0, (int) $s['daily_cap_minutes'] - $this->earnedToday($child));
        $room = min($room, max(0, (int) $s['bank_cap_minutes'] - $this->balance($child)));
        $earned = 0;
        $reasons = [];
        $capped = false;
        foreach ($wanted as $reason => $minutes) {
            $give = min($minutes, $room);
            $capped = $capped || $give < $minutes;
            if ($give <= 0) {
                continue;
            }
            $ref = $reason === 'streak' ? $today : $session->id;
            $row = RewardLedger::firstOrCreate(['child_id' => $child->id, 'reason' => $reason, 'ref_id' => $ref], ['minutes' => $give]);
            if ($row->wasRecentlyCreated) {
                $room -= $give;
                $earned += $give;
                $reasons[] = ['reason' => $reason, 'minutes' => $give];
            }
        }

        return ['earned' => $earned, 'reasons' => $reasons, 'balance_minutes' => $this->balance($child), 'capped' => $capped];
    }

    /** Consecutive days up to and including today with at least one practice session. */
    public function streakDays(Child $child): int
    {
        $days = $child->practiceSessions()->where('created_at', '>=', now()->subDays(30)->startOfDay())->pluck('created_at')->map(fn ($d) => $d->format('Y-m-d'))->unique()->flip();
        $n = 0;
        for ($d = now(); isset($days[$d->format('Y-m-d')]); $d = $d->copy()->subDay()) {
            $n++;
        }

        return $n;
    }

    public function bonus(Child $child, int $minutes, ?string $note, User $by): RewardLedger
    {
        return RewardLedger::create(['child_id' => $child->id, 'minutes' => $minutes, 'reason' => 'parent_bonus', 'note' => $note ? mb_substr($note, 0, 120) : null, 'created_by' => $by->id]);
    }

    /** Minutes spent playing. Never takes the balance below zero. */
    public function spend(Child $child, int $minutes, int $sessionId): int
    {
        $minutes = min($minutes, $this->balance($child));
        if ($minutes > 0) {
            RewardLedger::firstOrCreate(['child_id' => $child->id, 'reason' => 'play', 'ref_id' => $sessionId], ['minutes' => -$minutes]);
        }

        return $minutes;
    }
}
