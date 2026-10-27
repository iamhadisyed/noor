<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\GameItem;
use App\Models\GameProgress;
use App\Models\GameSession;
use App\Services\GameEngine;
use App\Services\MediaStore;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Games are a reward: practising earns play minutes, and a game can only be started while there are minutes to spend.
 * The app plays whole sessions offline from the rounds it was given, then reports the score.
 */
class GameController extends Controller
{
    public function __construct(private RewardService $rewards, private GameEngine $engine, private MediaStore $media) {}

    private function game(string $key): array
    {
        return config("games.games.{$key}") ?? abort(404);
    }

    /** The animals that have both a picture and a sound, with fresh signed links. */
    private function animals(): array
    {
        return GameItem::where('kind', 'animal')->where('active', true)->whereNotNull('image_path')->whereNotNull('audio_path')->orderBy('id')->get()
            ->map(fn ($i) => ['id' => $i->id, 'name_en' => $i->name_en, 'name_ur' => $i->name_ur, 'image_url' => $this->media->gameUrl($i, 'image'), 'audio_url' => $this->media->gameUrl($i, 'audio')])->all();
    }

    private function available(string $key, int $animals): bool
    {
        return ! $this->game($key)['needs_items'] || $animals >= (int) config('games.min_library_items');
    }

    /** All games, and whether this install has what each one needs. */
    public function index(): JsonResponse
    {
        $n = count($this->animals());

        return response()->json(['games' => collect(config('games.games'))->map(fn ($g, $k) => ['game' => $k, 'category' => $g['category'], 'title' => $g['title'], 'about' => $g['about'], 'available' => $this->available($k, $n)])->values()->all(), 'max_level' => config('games.max_level')]);
    }

    /** What the app needs to draw the games: the icon names and the animal pictures and sounds. */
    public function library(): JsonResponse
    {
        $icons = [];
        foreach (config('games.icons') as $cat => $set) {
            foreach ($set as $key => [$ur, $en]) { $icons[] = ['key' => $key, 'category' => $cat, 'name_en' => $en, 'name_ur' => $ur]; }
        }

        return response()->json(['icons' => $icons, 'animals' => $this->animals()]);
    }

    public function rewards(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $s = $this->rewards->settings($child);
        $progress = GameProgress::where('child_id', $child->id)->get()->keyBy('game');
        $n = count($this->animals());
        $balance = $this->rewards->balance($child);
        $enabled = $s['enabled_games'];

        return response()->json([
            'settings' => $s,
            'balance_minutes' => $balance,
            'earned_today' => $this->rewards->earnedToday($child),
            'streak_days' => $this->rewards->streakDays($child),
            'how_to_earn' => $this->howToEarn($s),
            'games' => collect(config('games.games'))->map(fn ($g, $k) => [
                'game' => $k, 'category' => $g['category'], 'title' => $g['title'],
                'enabled' => $enabled === null || in_array($k, $enabled, true), 'available' => $this->available($k, $n),
                'unlocked' => $s['enabled'] && $balance >= 1 && ($enabled === null || in_array($k, $enabled, true)) && $this->available($k, $n),
                'level' => $progress[$k]->level ?? 1, 'plays' => $progress[$k]->plays ?? 0, 'last_accuracy' => $progress[$k]->last_accuracy ?? null,
            ])->values()->all(),
            'ledger' => $child->rewardLedger()->orderByDesc('id')->limit(30)->get()->map(fn ($r) => ['id' => $r->id, 'minutes' => $r->minutes, 'reason' => $r->reason, 'note' => $r->note, 'created_at' => $r->created_at])->all(),
        ]);
    }

    private function howToEarn(array $s): array
    {
        $out = [['reason' => 'good_score', 'minutes' => (int) $s['minutes_per_good_session'], 'text' => "Score {$s['threshold_pct']}% or more in a practice session"],
            ['reason' => 'improvement', 'minutes' => (int) $s['improvement_minutes'], 'text' => "Beat your own recent average by {$s['improvement_points']} points"],
            ['reason' => 'streak', 'minutes' => (int) $s['streak_minutes'], 'text' => "Practise {$s['streak_days']} days in a row"]];
        if ($s['reward_for_effort']) {
            $out[] = ['reason' => 'effort', 'minutes' => (int) $s['effort_minutes'], 'text' => 'Finish a practice session'];
        }

        return $out;
    }

    public function updateSettings(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'threshold_pct' => 'sometimes|integer|between:30,100',
            'min_attempts' => 'sometimes|integer|between:1,30',
            'minutes_per_good_session' => 'sometimes|integer|between:1,30',
            'reward_for_effort' => 'sometimes|boolean',
            'effort_minutes' => 'sometimes|integer|between:1,15',
            'improvement_points' => 'sometimes|integer|between:3,50',
            'improvement_minutes' => 'sometimes|integer|between:0,20',
            'streak_days' => 'sometimes|integer|between:2,14',
            'streak_minutes' => 'sometimes|integer|between:0,20',
            'daily_cap_minutes' => 'sometimes|integer|between:5,120',
            'bank_cap_minutes' => 'sometimes|integer|between:5,240',
            'max_session_minutes' => 'sometimes|integer|between:1,60',
            'enabled_games' => 'sometimes|nullable|array',
            'enabled_games.*' => 'string|in:' . implode(',', array_keys(config('games.games'))),
        ]);
        $child->update(['reward_settings' => array_merge($child->reward_settings ?? [], $data)]);
        AuditLog::record($request->user(), 'child.reward_settings', $child, array_keys($data));

        return $this->rewards($request, $childId);
    }

    /** A parent can add minutes by hand, for good behaviour or a special day. */
    public function bonus(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $data = $request->validate(['minutes' => 'required|integer|between:1,60', 'note' => 'nullable|string|max:120']);
        $this->rewards->bonus($child, $data['minutes'], $data['note'] ?? null, $request->user());
        AuditLog::record($request->user(), 'child.reward_bonus', $child, ['minutes' => $data['minutes']]);

        return $this->rewards($request, $childId);
    }

    public function start(Request $request, int $childId, string $game): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $meta = $this->game($game);
        $s = $this->rewards->settings($child);
        $data = $request->validate(['rounds' => 'sometimes|integer|between:3,' . config('games.rounds_max')]);

        if (! $s['enabled'] || ($s['enabled_games'] !== null && ! in_array($game, $s['enabled_games'], true))) {
            return response()->json(['message' => 'This game is switched off for this child.', 'locked' => true, 'reason' => 'switched_off'], 423);
        }
        $animals = $meta['needs_items'] ? $this->animals() : [];
        if (! $this->available($game, count($animals))) {
            return response()->json(['message' => 'This game is not ready yet: it needs more animal pictures and sounds.', 'locked' => true, 'reason' => 'no_content'], 423);
        }
        $balance = $this->rewards->balance($child);
        if ($balance < 1) {
            return response()->json(['message' => 'No play time yet. Practise to earn some.', 'locked' => true, 'reason' => 'no_minutes', 'balance_minutes' => 0, 'how_to_earn' => $this->howToEarn($s)], 423);
        }

        // An abandoned game that never reported leaves nothing owed: only a finished game spends minutes.
        $level = GameProgress::where('child_id', $child->id)->where('game', $game)->value('level') ?? 1;
        $choices = min(4, max(2, (int) (($child->support_profile['max_choices'] ?? ($child->track === 'special' ? ChildController::SUPPORT_SPECIAL : ChildController::SUPPORT_STANDARD)['max_choices']))));
        $allowed = min($balance, (int) $s['max_session_minutes']) * 60;
        $session = GameSession::create(['child_id' => $child->id, 'game' => $game, 'level' => $level, 'allowed_seconds' => $allowed]);

        return response()->json([
            'session_id' => $session->id, 'game' => $game, 'level' => $level, 'allowed_seconds' => $allowed, 'balance_minutes' => $balance,
            'rounds' => $this->engine->rounds($game, $level, (int) ($data['rounds'] ?? config('games.rounds_default')), $choices, $animals),
        ], 201);
    }

    /** The app reports how it went. Spends the minutes and moves the level up or down. Safe to send twice. */
    public function finish(Request $request, int $id): JsonResponse
    {
        $session = GameSession::whereIn('child_id', $request->user()->accessibleChildren()->select('id'))->findOrFail($id);
        $child = Child::findOrFail($session->child_id);
        $data = $request->validate(['rounds_played' => 'required|integer|min:0|max:' . config('games.rounds_max'), 'correct' => 'required|integer|min:0|lte:rounds_played', 'seconds_played' => 'required|integer|min:0|max:86400']);

        if ($session->status === 'open') {
            $seconds = min($data['seconds_played'], $session->allowed_seconds);
            $minutes = $this->rewards->spend($child, (int) ceil($seconds / 60), $session->id);
            $session->update(['status' => 'finished', 'rounds_played' => $data['rounds_played'], 'correct' => $data['correct'], 'seconds_played' => $seconds, 'minutes_spent' => $minutes, 'finished_at' => now()]);
            $this->progress($child, $session);
        }
        $session->refresh();
        $progress = GameProgress::where('child_id', $child->id)->where('game', $session->game)->first();

        return response()->json([
            'minutes_spent' => $session->minutes_spent, 'balance_minutes' => $this->rewards->balance($child),
            'level' => $progress->level ?? $session->level, 'level_before' => $session->level, 'accuracy' => $session->rounds_played ? (int) round($session->correct / $session->rounds_played * 100) : null,
        ]);
    }

    /** Adaptive difficulty: climb after a strong session, ease off after a hard one, stay otherwise. */
    private function progress(Child $child, GameSession $session): void
    {
        $p = GameProgress::firstOrCreate(['child_id' => $child->id, 'game' => $session->game], ['level' => 1]);
        $p->plays++;
        if ($session->rounds_played >= 5) {
            $acc = $session->correct / $session->rounds_played;
            $p->last_accuracy = (int) round($acc * 100);
            $p->level = $acc >= 0.85 ? min(config('games.max_level'), $p->level + 1) : ($acc < 0.5 ? max(1, $p->level - 1) : $p->level);
        }
        $p->save();
    }
}
