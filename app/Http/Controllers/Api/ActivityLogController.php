<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CalmSession;
use App\Models\ChildNeedsLog;
use App\Models\GameSession;
use App\Models\PracticeSession;
use App\Models\RewardLedger;
use App\Services\ScreenTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "What did my child do?" One list of everything: practice, games, calm time, requests for help, play minutes earned, and screen time.
 * The free plan sees today; Pro sees up to two weeks and the screen time per day.
 */
class ActivityLogController extends Controller
{
    public function __construct(private ScreenTime $screen) {}

    public function index(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $pro = $this->screen->proFor($child);
        $tz = $request->query('tz', config('app.timezone'));
        try {
            now($tz);
        } catch (\Throwable) {
            $tz = config('app.timezone');
        }
        $asked = (int) $request->query('days', 1);
        $days = $pro ? max(1, min(14, $asked)) : 1;
        $from = now($tz)->subDays($days - 1)->startOfDay();
        $at = fn ($t) => $t->copy()->setTimezone($tz)->toIso8601String();
        $items = [];

        foreach (PracticeSession::where('child_id', $child->id)->where('created_at', '>=', $from)->get() as $p) {
            $items[] = ['kind' => 'practice', 'at' => $at($p->created_at), 'ts' => $p->created_at->timestamp, 'title' => $p->language === 'urdu' ? 'Urdu lesson practice' : 'English lesson practice',
                'minutes' => (int) round($p->duration_seconds / 60), 'detail' => $p->total_attempts ? "{$p->successful_attempts} of {$p->total_attempts} right" : null];
        }
        foreach (GameSession::where('child_id', $child->id)->where('status', 'finished')->where('created_at', '>=', $from)->get() as $g) {
            $title = config("games.games.{$g->game}.title.en", $g->game);
            $items[] = ['kind' => 'game', 'at' => $at($g->created_at), 'ts' => $g->created_at->timestamp, 'title' => "Played: {$title}", 'minutes' => (int) round(($g->seconds_played ?? 0) / 60),
                'detail' => $g->rounds_played ? "{$g->correct} of {$g->rounds_played} right first time" : null, 'game' => $g->game];
        }
        foreach (CalmSession::where('child_id', $child->id)->where('created_at', '>=', $from)->get() as $c) {
            $name = config("activities.{$c->activity}.title.en", $c->activity);
            $items[] = ['kind' => 'calm', 'at' => $at($c->created_at), 'ts' => $c->created_at->timestamp, 'title' => "Calm time: {$name}", 'minutes' => (int) round($c->seconds / 60), 'detail' => null];
        }
        foreach (ChildNeedsLog::where('child_id', $child->id)->where('created_at', '>=', $from)->get() as $n) {
            $items[] = ['kind' => 'need', 'at' => $at($n->created_at), 'ts' => $n->created_at->timestamp, 'title' => 'Asked for: ' . ucfirst(str_replace('_', ' ', $n->need_type)), 'minutes' => null,
                'detail' => $n->acknowledged ? 'You answered' : 'Not answered yet'];
        }
        foreach (RewardLedger::where('child_id', $child->id)->where('created_at', '>=', $from)->where('minutes', '>', 0)->get() as $r) {
            $items[] = ['kind' => 'reward', 'at' => $at($r->created_at), 'ts' => $r->created_at->timestamp, 'title' => "Earned {$r->minutes} play minutes", 'minutes' => null, 'detail' => str_replace('_', ' ', $r->reason)];
        }
        usort($items, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        $items = array_map(fn ($i) => array_diff_key($i, ['ts' => 1]), array_slice($items, 0, 200));

        $count = fn ($k) => count(array_filter($items, fn ($i) => $i['kind'] === $k));
        $summary = [
            'practice_minutes' => array_sum(array_column(array_filter($items, fn ($i) => $i['kind'] === 'practice'), 'minutes')),
            'game_minutes' => array_sum(array_column(array_filter($items, fn ($i) => $i['kind'] === 'game'), 'minutes')),
            'calm_sessions' => $count('calm'),
            'asked_for' => $count('need'),
        ];

        $out = ['days' => $days, 'limited' => ! $pro, 'summary' => $summary, 'items' => array_values($items)];
        if ($pro) {
            $out['screen_time'] = $this->screen->week($child, now($tz)->startOfDay(), $days);
        } else {
            $out['upgrade_hint'] = 'See the last two weeks and screen time with Kindo Pro.';
        }

        return response()->json($out);
    }
}
