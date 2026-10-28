<?php

namespace App\Services;

use App\Http\Controllers\Api\InsightsController;
use App\Models\Child;
use App\Models\GameProgress;
use Carbon\CarbonImmutable;

/**
 * The things worth celebrating, worked out from what the child has actually done.
 * Nothing is stored: a milestone exists exactly when the numbers support it, so a card can never claim more than is true.
 */
class Milestones
{
    public const STREAK = [3, 7, 14, 30, 60];
    public const MINUTES = [60, 300, 600, 1500, 3000];
    public const LETTERS = [1, 5, 10, 20, 28];
    public const GAME_LEVELS = [3, 5];

    /** @return list<array{key: string, kind: string, value: int, title: array{en: string, ur: string}, earned_on: ?string}> newest first */
    public function for(Child $child): array
    {
        $sessions = $child->practiceSessions()->orderBy('created_at')->get(['created_at', 'duration_seconds']);
        $out = [];

        // Longest run of consecutive practice days, and the day each streak length was first reached.
        $reached = [];
        $run = 0;
        $prev = null;
        foreach ($sessions->map(fn ($s) => $s->created_at->format('Y-m-d'))->unique() as $day) {
            $run = $prev && CarbonImmutable::parse($prev)->addDay()->format('Y-m-d') === $day ? $run + 1 : 1;
            $prev = $day;
            foreach (self::STREAK as $n) {
                if ($run >= $n) {
                    $reached[$n] ??= $day;
                }
            }
        }
        foreach ($reached as $n => $day) {
            $out[] = $this->m("streak_{$n}", 'streak', $n, ['en' => "{$n}-day practice streak", 'ur' => "{$n} دن مسلسل مشق"], $day);
        }

        // Total practice time, dated by the session that crossed each line.
        $secs = 0;
        $hit = [];
        foreach ($sessions as $s) {
            $secs += $s->duration_seconds;
            foreach (self::MINUTES as $n) {
                if ($secs >= $n * 60) {
                    $hit[$n] ??= $s->created_at->format('Y-m-d');
                }
            }
        }
        foreach ($hit as $n => $day) {
            $out[] = $this->m("minutes_{$n}", 'minutes', $n, ['en' => "{$n} minutes of practice", 'ur' => "{$n} منٹ کی مشق"], $day);
        }

        // Letters mastered (see InsightsController::letterStats for what mastered means).
        $letters = app(InsightsController::class)->letterStats($child, CarbonImmutable::now()->subYears(5), CarbonImmutable::now()->addDay());
        $lastLetter = $child->practiceSessions()->whereHas('letters')->max('created_at');
        foreach (self::LETTERS as $n) {
            if ($letters['mastered'] >= $n) {
                $out[] = $this->m("letters_{$n}", 'letters', $n, ['en' => $n === 1 ? 'First letter mastered' : "{$n} letters mastered", 'ur' => $n === 1 ? 'پہلا حرف سیکھ لیا' : "{$n} حروف سیکھ لیے"], $lastLetter ? substr((string) $lastLetter, 0, 10) : null);
            }
        }

        // Game levels reached, per game.
        foreach (GameProgress::where('child_id', $child->id)->get() as $g) {
            $def = config("games.games.{$g->game}");
            if (! $def) {
                continue;
            }
            foreach (self::GAME_LEVELS as $n) {
                if ($g->level >= $n) {
                    $out[] = $this->m("game_{$g->game}_{$n}", 'game', $n, ['en' => "Level {$n} in {$def['title']['en']}", 'ur' => "{$def['title']['ur']} میں لیول {$n}"], $g->updated_at?->format('Y-m-d'));
                }
            }
        }

        usort($out, fn ($a, $b) => [$b['earned_on'] ?? '', $b['value']] <=> [$a['earned_on'] ?? '', $a['value']]);

        return $out;
    }

    public function find(Child $child, string $key): ?array
    {
        return collect($this->for($child))->firstWhere('key', $key);
    }

    private function m(string $key, string $kind, int $value, array $title, ?string $on): array
    {
        return ['key' => $key, 'kind' => $kind, 'value' => $value, 'title' => $title, 'earned_on' => $on];
    }
}
