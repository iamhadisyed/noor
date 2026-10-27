<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\GameItem;
use App\Models\GameProgress;
use App\Models\PracticeSession;
use App\Models\RewardLedger;
use App\Models\User;
use App\Services\GameEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GamesTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private array $child;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->parent = User::factory()->create(['subscription_tier' => 'pro']);
        $this->as($this->parent);
        $this->child = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => '2020-01-01'])->json();
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function wav(): UploadedFile
    {
        $data = str_repeat("\x00\x00", 2000);
        $hdr = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16) . 'data' . pack('V', strlen($data));
        $path = tempnam(sys_get_temp_dir(), 'snd') . '.wav';
        file_put_contents($path, $hdr . $data);

        return new UploadedFile($path, 'roar.wav', 'audio/wav', null, true);
    }

    /** Animals with both a picture and a sound, enough for the listening games. */
    private function library(int $n = 5): void
    {
        $this->seed(\Database\Seeders\GameItemSeeder::class);
        $admin = User::factory()->create(['role' => 'super_admin']);
        foreach (GameItem::orderBy('id')->limit($n)->get() as $item) {
            $this->as($admin)->post("/api/v1/admin/games/items/{$item->id}", ['image' => UploadedFile::fake()->image('a.jpg', 300, 300), 'audio' => $this->wav()], ['Accept' => 'application/json'])->assertOk();
        }
        $this->as($this->parent);
    }

    private function practice(int $total, int $good, ?string $when = null): array
    {
        $res = $this->postJson("/api/v1/children/{$this->child['id']}/sessions", ['language' => 'urdu', 'total_attempts' => $total, 'successful_attempts' => $good, 'duration_seconds' => 120])->assertCreated();
        if ($when) {
            PracticeSession::whereKey($res->json('id'))->update(['created_at' => $when]);
        }

        return $res->json();
    }

    private function balance(): int
    {
        return $this->getJson("/api/v1/children/{$this->child['id']}/rewards")->json('balance_minutes');
    }

    // ---------- the game engine ----------

    private function animals(int $n = 6): array
    {
        return array_map(fn ($i) => ['id' => $i, 'name_en' => "A{$i}", 'name_ur' => "ج{$i}", 'image_url' => "i{$i}", 'audio_url' => "s{$i}"], range(1, $n));
    }

    public function test_every_round_of_every_game_is_valid_at_every_level_and_choice_count(): void
    {
        $engine = new GameEngine();
        foreach (array_keys(config('games.games')) as $game) {
            foreach (range(1, 5) as $level) {
                foreach ([2, 3, 4] as $choices) {
                    foreach ($engine->rounds($game, $level, 15, $choices, $this->animals()) as $r) {
                        if ($game === 'memory_match') {
                            $this->assertCount($r['cards'] / 2, $r['pairs']);
                            $this->assertSame(count($r['pairs']), count(array_unique(array_column($r['pairs'], 'key'))));
                            continue;
                        }
                        $opts = $r['options'];
                        // "odd one out" needs at least three things to compare
                        $this->assertCount($game === 'odd_one_out' ? max(3, $choices) : $choices, $opts, "{$game} L{$level} choices");
                        $this->assertSame(count($opts), count(array_unique(array_map('json_encode', $opts))), "{$game} duplicate options");
                        $this->assertArrayHasKey($r['answer_index'], $opts, "{$game} answer index");
                    }
                }
            }
        }
    }

    public function test_the_maths_is_right(): void
    {
        $engine = new GameEngine();
        foreach (range(1, 5) as $level) {
            foreach ($engine->rounds('add_sub', $level, 40, 3) as $r) {
                $want = $r['op'] === '+' ? $r['a'] + $r['b'] : $r['a'] - $r['b'];
                $this->assertSame($want, $r['options'][$r['answer_index']]);
                $this->assertGreaterThanOrEqual(0, $want);
                $this->assertSame("{$r['a']} {$r['op']} {$r['b']}", $r['expression']);
                if ($level === 1) { $this->assertSame('+', $r['op']); $this->assertLessThanOrEqual(5, $want); }
            }
            foreach ($engine->rounds('count', $level, 40, 3) as $r) {
                $this->assertSame($r['count'], $r['options'][$r['answer_index']]);
                $this->assertGreaterThanOrEqual(1, $r['count']);
            }
            foreach ($engine->rounds('number_order', $level, 40, 3) as $r) {
                $seq = $r['sequence'];
                $this->assertNull($seq[$r['missing_index']]);
                $this->assertNotNull($seq[0]);
                $this->assertNotNull($seq[4]);
                $filled = $seq;
                $filled[$r['missing_index']] = $r['options'][$r['answer_index']];
                $diffs = array_unique(array_map(fn ($i) => $filled[$i + 1] - $filled[$i], range(0, 3)));
                $this->assertCount(1, $diffs, 'the sequence is steady once the gap is filled');
            }
        }
    }

    public function test_the_odd_one_out_and_the_pattern_have_one_right_answer(): void
    {
        $engine = new GameEngine();
        foreach (range(1, 5) as $level) {
            foreach ($engine->rounds('odd_one_out', $level, 30, 4) as $r) {
                $cats = array_count_values(array_column($r['options'], 'category'));
                $this->assertSame(2, count($cats));
                $this->assertSame(1, min($cats));
                $this->assertSame(1, array_count_values(array_column($r['options'], 'category'))[$r['options'][$r['answer_index']]['category']]);
            }
            foreach ($engine->rounds('pattern', $level, 30, 3) as $r) {
                $seq = $r['sequence'];
                $this->assertNull(end($seq));
                $keys = array_map(fn ($x) => $x['key'] ?? null, $seq);
                $unit = [1 => 2, 2 => 3, 3 => 3, 4 => 3, 5 => 4][$level];
                $answer = $r['options'][$r['answer_index']]['key'];
                $i = count($keys) - 1;
                $this->assertSame($keys[$i - $unit], $answer, "L{$level}: the next item repeats the unit");
            }
        }
    }

    public function test_the_sound_games_pair_each_sound_with_its_own_animal(): void
    {
        $engine = new GameEngine();
        $animals = $this->animals();
        foreach ($engine->rounds('animal_sound', 1, 25, 4, $animals) as $r) {
            $right = $r['options'][$r['answer_index']];
            $this->assertSame("s{$right['item_id']}", $r['audio_url']);
            $this->assertSame($r['item_id'], $right['item_id']);
        }
        foreach ($engine->rounds('sound_guess', 1, 25, 4, $animals) as $r) {
            $right = $r['options'][$r['answer_index']];
            $this->assertSame($r['item_id'], $right['item_id']);
            $this->assertSame("i{$r['item_id']}", $r['image_url']);
        }
    }

    // ---------- earning ----------

    public function test_a_good_session_earns_minutes_and_a_poor_one_does_not(): void
    {
        $this->assertSame(0, $this->balance());
        $this->practice(10, 4);                                                  // 40%
        $this->assertSame(0, $this->balance());
        $res = $this->practice(10, 8);                                           // 80%
        $this->assertSame(5, $res['reward']['earned']);
        $this->assertSame('good_score', $res['reward']['reasons'][0]['reason']);
        $this->assertSame(5, $this->balance());
    }

    public function test_tiny_sessions_cannot_be_farmed(): void
    {
        $this->assertSame(0, $this->practice(2, 2)['reward']['earned']);          // under min attempts
        $this->assertSame(0, $this->balance());
    }

    public function test_special_track_rules(): void
    {
        $kid = Child::findOrFail($this->child['id']);
        $kid->update(['track' => 'special']);
        $res = $this->practice(10, 6);                                           // 60% meets a special child's bar
        $this->assertSame('good_score', $res['reward']['reasons'][0]['reason']);
        $res2 = $this->practice(10, 3);                                          // below the bar: a little for trying
        $this->assertSame('effort', $res2['reward']['reasons'][0]['reason']);
        $this->assertSame(2, $res2['reward']['earned']);
    }

    public function test_improving_on_your_own_average_earns_a_bonus_even_below_the_usual_bar(): void
    {
        $this->patchJson("/api/v1/children/{$this->child['id']}/rewards/settings", ['threshold_pct' => 90])->assertOk();
        foreach ([2, 3, 2, 3] as $good) { $this->practice(10, $good); }          // steady 20-30%
        $res = $this->practice(10, 6);                                           // 60%: not 90, but far above their own average
        $this->assertSame(['improvement'], array_column($res['reward']['reasons'], 'reason'));
        $this->assertSame(3, $res['reward']['earned']);
    }

    public function test_practising_several_days_in_a_row_earns_a_streak_bonus_once_a_day(): void
    {
        $this->practice(10, 1, now()->subDays(2)->toDateTimeString());
        $this->practice(10, 1, now()->subDay()->toDateTimeString());
        $today = $this->practice(10, 1);
        $this->assertSame(['streak'], array_column($today['reward']['reasons'], 'reason'));
        $again = $this->practice(10, 1);
        $this->assertSame([], $again['reward']['reasons']);                       // not twice in one day
    }

    public function test_the_daily_cap_and_the_savings_cap_hold(): void
    {
        $this->patchJson("/api/v1/children/{$this->child['id']}/rewards/settings", ['daily_cap_minutes' => 8, 'bank_cap_minutes' => 100])->assertOk();
        $this->assertSame(5, $this->practice(10, 9)['reward']['earned']);
        $second = $this->practice(10, 9);
        $this->assertSame(3, $second['reward']['earned']);                        // only 3 left today
        $this->assertTrue($second['reward']['capped']);
        $this->assertSame(0, $this->practice(10, 9)['reward']['earned']);
        $this->assertSame(8, $this->balance());
    }

    public function test_a_retried_upload_cannot_pay_twice(): void
    {
        $h = ['Idempotency-Key' => 'session-key-0001'];
        $body = ['language' => 'urdu', 'total_attempts' => 10, 'successful_attempts' => 9, 'duration_seconds' => 90];
        $first = $this->postJson("/api/v1/children/{$this->child['id']}/sessions", $body, $h)->assertCreated();
        $this->postJson("/api/v1/children/{$this->child['id']}/sessions", $body, $h)->assertCreated()->assertHeader('Idempotent-Replay', 'true');
        $this->assertSame(5, $this->balance());
        $this->assertSame(1, RewardLedger::where('reason', 'good_score')->count());
        $this->assertNotNull($first->json('id'));
    }

    public function test_the_parent_can_switch_rewards_off_and_add_bonus_minutes(): void
    {
        $this->patchJson("/api/v1/children/{$this->child['id']}/rewards/settings", ['enabled' => false])->assertOk();
        $this->assertSame(0, $this->practice(10, 10)['reward']['earned']);

        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 10, 'note' => 'Great week'])->assertOk()->assertJsonPath('balance_minutes', 10);
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 500])->assertStatus(422);
        $this->assertTrue(\App\Models\AuditLog::where('action', 'child.reward_bonus')->exists());
    }

    public function test_settings_are_validated_and_private(): void
    {
        $this->patchJson("/api/v1/children/{$this->child['id']}/rewards/settings", ['threshold_pct' => 5])->assertStatus(422);
        $this->patchJson("/api/v1/children/{$this->child['id']}/rewards/settings", ['enabled_games' => ['nope']])->assertStatus(422);
        $this->as(User::factory()->create());
        $this->getJson("/api/v1/children/{$this->child['id']}/rewards")->assertStatus(404);
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 5])->assertStatus(404);
    }

    // ---------- playing ----------

    public function test_games_stay_locked_until_there_are_minutes(): void
    {
        $res = $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertStatus(423);
        $res->assertJsonPath('reason', 'no_minutes')->assertJsonPath('locked', true);
        $this->assertNotEmpty($res->json('how_to_earn'));
        $r = $this->getJson("/api/v1/children/{$this->child['id']}/rewards");
        $this->assertFalse($r->json('games.0.unlocked'));

        $this->practice(10, 9);
        $this->assertTrue($this->getJson("/api/v1/children/{$this->child['id']}/rewards")->json('games.0.unlocked'));
    }

    public function test_a_game_session_runs_to_the_end_and_spends_only_the_time_played(): void
    {
        $this->practice(10, 9);                                                   // 5 minutes
        $start = $this->postJson("/api/v1/children/{$this->child['id']}/games/add_sub/start", ['rounds' => 6])->assertCreated();
        $this->assertCount(6, $start->json('rounds'));
        $this->assertSame(300, $start->json('allowed_seconds'));
        $this->assertSame(1, $start->json('level'));

        $fin = $this->postJson("/api/v1/game-sessions/{$start->json('session_id')}/finish", ['rounds_played' => 6, 'correct' => 6, 'seconds_played' => 130])->assertOk();
        $fin->assertJsonPath('minutes_spent', 3)->assertJsonPath('balance_minutes', 2)->assertJsonPath('level', 2)->assertJsonPath('accuracy', 100);

        // the same report again changes nothing
        $this->postJson("/api/v1/game-sessions/{$start->json('session_id')}/finish", ['rounds_played' => 6, 'correct' => 0, 'seconds_played' => 9999])->assertOk()->assertJsonPath('balance_minutes', 2);
    }

    public function test_time_is_capped_by_what_was_allowed_and_the_balance_never_goes_negative(): void
    {
        $this->practice(10, 9);
        $start = $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertCreated();
        $this->postJson("/api/v1/game-sessions/{$start->json('session_id')}/finish", ['rounds_played' => 10, 'correct' => 5, 'seconds_played' => 5000])->assertOk()->assertJsonPath('minutes_spent', 5)->assertJsonPath('balance_minutes', 0);
        $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertStatus(423);
    }

    public function test_the_longest_single_play_is_a_parent_setting(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 40]);
        $this->patchJson("/api/v1/children/{$this->child['id']}/rewards/settings", ['max_session_minutes' => 10])->assertOk();
        $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertCreated()->assertJsonPath('allowed_seconds', 600);
    }

    public function test_difficulty_follows_the_child(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 60]);
        $play = function (int $correct) {
            $s = $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertCreated()->json('session_id');
            return $this->postJson("/api/v1/game-sessions/{$s}/finish", ['rounds_played' => 10, 'correct' => $correct, 'seconds_played' => 30])->json('level');
        };
        $this->assertSame(2, $play(10));
        $this->assertSame(3, $play(9));
        $this->assertSame(3, $play(7));      // middling: stay
        $this->assertSame(2, $play(3));      // struggling: ease off
        $this->assertSame(1, $play(2));
        $this->assertSame(1, $play(0));      // never below 1
        $this->assertSame(6, GameProgress::where('game', 'count')->value('plays'));
    }

    public function test_a_very_short_play_does_not_change_the_level(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 10]);
        $s = $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->json('session_id');
        $this->postJson("/api/v1/game-sessions/{$s}/finish", ['rounds_played' => 3, 'correct' => 3, 'seconds_played' => 10])->assertJsonPath('level', 1);
    }

    public function test_choices_follow_the_childs_support_profile(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 10]);
        $this->patchJson("/api/v1/children/{$this->child['id']}", ['support_profile' => ['max_choices' => 2]])->assertOk();
        foreach ($this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->json('rounds') as $r) {
            $this->assertCount(2, $r['options']);
        }
    }

    public function test_a_parent_can_switch_a_single_game_off(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 10]);
        $this->patchJson("/api/v1/children/{$this->child['id']}/rewards/settings", ['enabled_games' => ['count']])->assertOk();
        $this->postJson("/api/v1/children/{$this->child['id']}/games/add_sub/start")->assertStatus(423)->assertJsonPath('reason', 'switched_off');
        $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertCreated();
    }

    public function test_another_parent_cannot_play_or_report_for_this_child(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 10]);
        $s = $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->json('session_id');
        $this->as(User::factory()->create());
        $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertStatus(404);
        $this->postJson("/api/v1/game-sessions/{$s}/finish", ['rounds_played' => 5, 'correct' => 5, 'seconds_played' => 5])->assertStatus(404);
    }

    public function test_an_unknown_game_is_not_found(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/games/nope/start")->assertStatus(404);
    }

    // ---------- the animal sound games need real content ----------

    public function test_sound_games_stay_closed_until_the_library_has_enough_complete_animals(): void
    {
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 10]);
        $this->postJson("/api/v1/children/{$this->child['id']}/games/animal_sound/start")->assertStatus(423)->assertJsonPath('reason', 'no_content');
        $this->assertFalse(collect($this->getJson('/api/v1/games')->json('games'))->firstWhere('game', 'animal_sound')['available']);
        $this->assertTrue(collect($this->getJson('/api/v1/games')->json('games'))->firstWhere('game', 'count')['available']);

        $this->library(3);                                                        // not enough
        $this->postJson("/api/v1/children/{$this->child['id']}/games/animal_sound/start")->assertStatus(423);
        $this->library(5);
        $start = $this->postJson("/api/v1/children/{$this->child['id']}/games/animal_sound/start", ['rounds' => 4])->assertCreated();
        $r = $start->json('rounds.0');
        $this->assertStringContainsString('signature=', $r['audio_url']);
        $this->get($r['audio_url'])->assertOk();
        $this->get($r['options'][0]['image_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->postJson("/api/v1/children/{$this->child['id']}/games/sound_guess/start")->assertCreated();
    }

    public function test_the_library_endpoint_lists_icons_and_only_complete_animals(): void
    {
        $this->library(5);
        $lib = $this->getJson('/api/v1/games/library')->assertOk();
        $this->assertCount(5, $lib->json('animals'));
        $this->assertNotEmpty($lib->json('icons'));
        $this->assertArrayNotHasKey('image_path', $lib->json('animals.0'));
    }

    // ---------- the administrator's library ----------

    public function test_only_the_super_admin_manages_the_library_and_it_checks_files(): void
    {
        $this->seed(\Database\Seeders\GameItemSeeder::class);
        $this->getJson('/api/v1/admin/games/items')->assertStatus(403);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $list = $this->as($admin)->getJson('/api/v1/admin/games/items')->assertOk();
        $this->assertSame(0, $list->json('complete'));
        $this->assertSame(12, count($list->json('items')));

        $item = GameItem::where('slug', 'lion')->firstOrFail();
        $this->post("/api/v1/admin/games/items/{$item->id}", ['audio' => UploadedFile::fake()->createWithContent('roar.mp3', '<?php evil();')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/v1/admin/games/items/{$item->id}", ['image' => UploadedFile::fake()->createWithContent('lion.jpg', 'not a picture')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertFalse($item->fresh()->isComplete());

        $this->post("/api/v1/admin/games/items/{$item->id}", ['image' => UploadedFile::fake()->image('lion.jpg', 1000, 800), 'audio' => $this->wav()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('complete', true);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($item->fresh()->image_path));
        $this->assertSame(600, max($w, $h));

        $this->patchJson("/api/v1/admin/games/items/{$item->id}", ['active' => false])->assertStatus(405);   // updates are POST (multipart)
        $this->post("/api/v1/admin/games/items/{$item->id}", ['active' => false], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('complete', false);
        $this->assertTrue(\App\Models\AuditLog::where('action', 'admin.game_item_updated')->exists());
    }

    public function test_the_admin_can_add_and_remove_an_animal_and_files_go_with_it(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->as($admin);
        $id = $this->postJson('/api/v1/admin/games/items', ['name_en' => 'Parrot', 'name_ur' => 'طوطا'])->assertCreated()->assertJsonPath('slug', 'parrot')->json('id');
        $this->postJson('/api/v1/admin/games/items', ['name_en' => 'Parrot', 'name_ur' => 'طوطا'])->assertCreated()->assertJsonPath('slug', 'parrot-2');
        $this->post("/api/v1/admin/games/items/{$id}", ['image' => UploadedFile::fake()->image('p.jpg'), 'audio' => $this->wav()], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotSame([], Storage::disk('local')->allFiles());

        $this->deleteJson("/api/v1/admin/games/items/{$id}")->assertOk();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unsigned_game_media_is_refused(): void
    {
        $this->library(1);
        $item = GameItem::whereNotNull('image_path')->first();
        $this->get("/api/v1/media/game/{$item->id}/image")->assertStatus(403);
    }

    // ---------- syncing the balance ----------

    public function test_the_balance_shows_on_the_child_and_reaches_the_phone_through_sync(): void
    {
        $cursor = $this->getJson('/api/v1/sync')->json('cursor');
        $this->practice(10, 9);

        $res = $this->getJson("/api/v1/sync?cursor={$cursor}");
        $child = collect($res->json('changes'))->firstWhere('entity', 'child');
        $this->assertSame(5, $child['data']['rewards']['balance_minutes']);
        $this->assertSame(5, $this->getJson("/api/v1/children/{$this->child['id']}")->json('rewards.balance_minutes'));
    }
}
