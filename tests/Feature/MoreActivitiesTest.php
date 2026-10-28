<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\User;
use App\Services\GameEngine;
use App\Support\ChildKeyboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoreActivitiesTest extends TestCase
{
    use RefreshDatabase;

    private GameEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new GameEngine();
    }

    private function rounds(string $game, int $level, int $n = 30, int $choices = 3, string $lang = 'en'): array
    {
        return $this->engine->rounds($game, $level, $n, $choices, [], $lang);
    }

    public function test_the_new_games_offer_the_right_number_of_distinct_choices_with_one_valid_answer(): void
    {
        foreach (['first_letter', 'alphabet_gap', 'opposites', 'calendar', 'colors', 'which_group', 'match_same', 'more_less', 'feelings'] as $game) {
            foreach (range(1, 5) as $level) {
                foreach ([2, 3, 4] as $choices) {
                    foreach (['en', 'ur'] as $lang) {
                        foreach ($this->rounds($game, $level, 12, $choices, $lang) as $r) {
                            $this->assertCount($choices, $r['options'], "{$game} L{$level}");
                            $this->assertSame(count($r['options']), count(array_unique(array_map('json_encode', $r['options']))), "{$game} L{$level} duplicate options");
                            $this->assertArrayHasKey($r['answer_index'], $r['options'], "{$game} answer index");
                            $this->assertNotEmpty($r['prompt']['en']);
                            $this->assertNotEmpty($r['prompt']['ur']);
                        }
                    }
                }
            }
        }
    }

    public function test_first_letter_asks_for_the_real_first_letter_in_both_languages(): void
    {
        foreach (['en', 'ur'] as $lang) {
            foreach (range(1, 5) as $level) {
                foreach ($this->rounds('first_letter', $level, 40, 4, $lang) as $r) {
                    $name = $lang === 'ur' ? $r['icon']['name_ur'] : mb_strtolower($r['icon']['name_en']);
                    $this->assertSame(mb_substr($name, 0, 1), $r['options'][$r['answer_index']]);
                    $this->assertContains($r['options'][$r['answer_index']], $lang === 'ur' ? ChildKeyboard::URDU : ChildKeyboard::ENGLISH);
                }
            }
        }
    }

    public function test_spell_it_gives_a_word_the_child_can_type_and_gets_longer_with_the_level(): void
    {
        $max = [1 => 3, 2 => 4, 3 => 5, 4 => 6, 5 => 8];
        foreach (['en', 'ur'] as $lang) {
            foreach (range(1, 5) as $level) {
                foreach ($this->rounds('spell_picture', $level, 40, 3, $lang) as $r) {
                    $this->assertSame([], ChildKeyboard::unsupported($r['target']), "{$r['target']} must be typeable");
                    $this->assertLessThanOrEqual($max[$level], mb_strlen($r['target']));
                    $this->assertSame(ChildKeyboard::layoutFor($r['target']), $r['keyboard']);
                    $this->assertSame(mb_strlen($r['target']), $r['letters_in_word']);
                    $this->assertSame($level <= 2, $r['first_letter_hint']);
                    $name = $r['lang'] === 'ur' ? $r['icon']['name_ur'] : mb_strtolower($r['icon']['name_en']);
                    $this->assertSame($name, $r['target'], 'the word is the name of the picture shown');
                }
            }
        }
    }

    public function test_the_missing_letter_is_the_one_that_belongs_in_the_alphabet(): void
    {
        foreach (['en' => ChildKeyboard::ENGLISH, 'ur' => ChildKeyboard::URDU] as $lang => $abc) {
            foreach (range(1, 5) as $level) {
                foreach ($this->rounds('alphabet_gap', $level, 40, 3, $lang) as $r) {
                    $seq = $r['sequence'];
                    $this->assertNull($seq[$r['missing_index']]);
                    $this->assertGreaterThan(0, $r['missing_index']);
                    $this->assertLessThan(count($seq) - 1, $r['missing_index'], 'never the first or last, so there is always a neighbour to read');
                    $answer = $r['options'][$r['answer_index']];
                    $neighbour = $seq[$r['missing_index'] - 1];
                    $step = abs(array_search($answer, $abc, true) - array_search($neighbour, $abc, true));
                    $this->assertSame(1, $step, 'the answer sits right next to its neighbour in the alphabet');
                    foreach ($seq as $l) { $l === null || $this->assertNotSame($l, $answer); }
                }
            }
        }
    }

    public function test_the_opposite_is_really_the_opposite(): void
    {
        $pairs = [];
        foreach (config('games.opposites') as [$a, $au, $b, $bu]) { $pairs[$a] = $b; $pairs[$b] = $a; $pairs[$au] = $bu; $pairs[$bu] = $au; }
        foreach (['en', 'ur'] as $lang) {
            foreach (range(1, 5) as $level) {
                foreach ($this->rounds('opposites', $level, 40, 4, $lang) as $r) {
                    $this->assertSame($pairs[$r['word'][$lang]], $r['options'][$r['answer_index']]);
                }
            }
        }
    }

    public function test_days_and_months_come_in_the_right_order(): void
    {
        foreach (range(1, 5) as $level) {
            foreach ($this->rounds('calendar', $level, 60, 3) as $r) {
                $list = array_column(config($r['kind'] === 'month' ? 'games.months' : 'games.days'), 0);
                $i = array_search($r['given']['en'], $list, true);
                $expect = $list[($i + ($r['relation'] === 'after' ? 1 : -1) + count($list)) % count($list)];
                $this->assertSame($expect, $r['options'][$r['answer_index']]['en']);
                $this->assertSame($level >= 4 ? 'month' : 'day', $r['kind']);
            }
        }
    }

    public function test_colours_start_with_a_few_clear_ones_and_the_answer_is_the_named_colour(): void
    {
        foreach (range(1, 5) as $level) {
            foreach ($this->rounds('colors', $level, 40, 4) as $r) {
                $this->assertSame($r['target']['key'], $r['options'][$r['answer_index']]['key']);
                $this->assertSame($level <= 2 ? 'find' : 'name', $r['mode']);
                if ($level <= 2) {
                    $this->assertContains($r['target']['key'], ['red', 'blue', 'green', 'yellow']);
                    foreach ($r['options'] as $o) { $this->assertContains($o['key'], ['red', 'blue', 'green', 'yellow']); }
                }
                $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $r['target']['hex']);
            }
        }
    }

    public function test_which_group_has_exactly_one_right_answer(): void
    {
        foreach ($this->rounds('which_group', 1, 60, 4) as $r) {
            $cats = array_column($r['options'], 'category');
            $this->assertSame(count($cats), count(array_unique($cats)), 'one picture from each kind');
            $this->assertSame($r['group'], $r['options'][$r['answer_index']]['category']);
            $this->assertSame(1, count(array_keys($cats, $r['group'], true)));
        }
    }

    public function test_find_the_same_has_one_identical_picture_and_gets_more_alike_with_the_level(): void
    {
        foreach (range(1, 5) as $level) {
            foreach ($this->rounds('match_same', $level, 40, 4) as $r) {
                $this->assertSame($r['target']['key'], $r['options'][$r['answer_index']]['key']);
                $this->assertSame(1, count(array_keys(array_column($r['options'], 'key'), $r['target']['key'], true)));
                $others = array_filter($r['options'], fn ($o) => $o['key'] !== $r['target']['key']);
                if ($level <= 2) {
                    foreach ($others as $o) { $this->assertNotSame($r['target']['category'], $o['category'], 'easy: very different pictures'); }
                } elseif ($level >= 3) {
                    foreach ($others as $o) { $this->assertSame($r['target']['category'], $o['category'], 'harder: the same kind of thing'); }
                }
            }
        }
    }

    public function test_more_or_fewer_picks_the_biggest_or_smallest_group(): void
    {
        foreach (range(1, 5) as $level) {
            foreach ([2, 3, 4] as $choices) {
                foreach ($this->rounds('more_less', $level, 40, $choices) as $r) {
                    $counts = array_column($r['options'], 'count');
                    $this->assertSame(count($counts), count(array_unique($counts)), 'no ties');
                    $this->assertSame($r['ask'] === 'fewest' ? min($counts) : max($counts), $counts[$r['answer_index']]);
                    if ($level === 1) { $this->assertSame('most', $r['ask'], 'the first level only asks for the most'); }
                }
            }
        }
    }

    public function test_feelings_start_with_four_clear_ones_and_both_directions_have_one_answer(): void
    {
        $modes = [];
        foreach (range(1, 5) as $level) {
            foreach ($this->rounds('feelings', $level, 60, 4) as $r) {
                $modes[$r['mode']] = true;
                $this->assertSame($r['target']['key'], $r['options'][$r['answer_index']]['key']);
                if ($level <= 2) {
                    $this->assertSame('name', $r['mode']);
                    foreach ($r['options'] as $o) { $this->assertContains($o['key'], ['happy', 'sad', 'angry', 'scared']); }
                }
            }
        }
        $this->assertSame(['face', 'name'], collect($modes)->keys()->sort()->values()->all());
    }

    public function test_routines_are_whole_ordered_slices_and_never_start_in_order(): void
    {
        $want = [1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 5];
        foreach (range(1, 5) as $level) {
            foreach ($this->rounds('routine_order', $level, 40) as $r) {
                $all = array_keys(config("games.routines.{$r['routine']['key']}.steps"));
                $order = $r['answer_order'];
                $this->assertSame(min($want[$level], count($all)), count($order));
                $start = array_search($order[0], $all, true);
                $this->assertSame(array_slice($all, $start, count($order)), $order, 'a real stretch of the routine, in order');
                $shown = array_column($r['steps'], 'key');
                $this->assertEqualsCanonicalizing($order, $shown);
                $this->assertNotSame($order, $shown, 'the order must be worked out, not given');
            }
        }
    }

    // ---------- through the API ----------

    private function parent(string $neuro = 'neurotypical'): array
    {
        $u = User::factory()->create();
        Sanctum::actingAs($u);
        $c = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => now()->subYears(6)->toDateString(), 'neurotype' => $neuro])->json();
        $this->postJson("/api/v1/children/{$c['id']}/rewards/bonus", ['minutes' => 30]);

        return $c;
    }

    public function test_every_new_game_can_be_started_and_finished_through_the_api(): void
    {
        $c = $this->parent();
        $games = collect($this->getJson("/api/v1/children/{$c['id']}/rewards")->assertOk()->json('games'));
        $this->assertGreaterThanOrEqual(19, $games->count());

        foreach (['first_letter', 'spell_picture', 'alphabet_gap', 'opposites', 'calendar', 'colors', 'which_group', 'match_same', 'more_less', 'feelings', 'routine_order'] as $g) {
            $this->assertTrue($games->firstWhere('game', $g)['unlocked'], "{$g} should be playable");
            $r = $this->postJson("/api/v1/children/{$c['id']}/games/{$g}/start", ['rounds' => 5, 'language' => 'ur'])->assertCreated()->json();
            $this->assertCount(5, $r['rounds']);
            $this->postJson("/api/v1/game-sessions/{$r['session_id']}/finish", ['rounds_played' => 5, 'correct' => 4, 'seconds_played' => 30])->assertOk();
        }
        $this->postJson("/api/v1/children/{$c['id']}/games/first_letter/start", ['language' => 'fr'])->assertUnprocessable();
    }

    public function test_games_say_which_children_they_suit_best_and_the_library_lists_the_new_pictures(): void
    {
        $c = $this->parent('autism_spectrum');
        $games = collect($this->getJson("/api/v1/children/{$c['id']}/rewards")->json('games'))->keyBy('game');

        $this->assertSame(['special'], $games['feelings']['for']);
        $this->assertSame(['special'], $games['routine_order']['for']);
        $this->assertSame(['standard'], $games['spell_picture']['for']);
        $this->assertSame(['standard', 'special'], $games['colors']['for']);

        $lib = $this->getJson('/api/v1/games/library')->assertOk()->json();
        $this->assertCount(6, $lib['feelings']);
        $this->assertCount(10, $lib['colors']);
        $this->assertSame(['morning', 'bedtime', 'hand_washing', 'meal'], array_column($lib['routines'], 'key'));
        $this->assertSame('Wake up', $lib['routines'][0]['steps'][0]['name_en']);
    }

    // ---------- calm activities ----------

    public function test_calm_activities_are_listed_with_steps_in_both_languages_and_are_never_locked(): void
    {
        $c = $this->parent();   // a child with no play minutes at all can still use them
        $list = collect($this->getJson('/api/v1/activities')->assertOk()->json('activities'))->keyBy('activity');

        $this->assertEqualsCanonicalizing(['breathing_bubble', 'five_senses', 'squeeze_release', 'slow_count', 'movement_break'], $list->keys()->all());
        foreach ($list as $a) {
            $this->assertNotEmpty($a['title']['en']);
            $this->assertNotEmpty($a['title']['ur']);
            $this->assertNotEmpty($a['steps']);
            foreach ($a['steps'] as $s) {
                $this->assertContains($s['cue'], ['in', 'hold', 'out', 'rest', 'do']);
                $this->assertGreaterThan(0, $s['seconds']);
                $this->assertNotSame('', $s['text']['en']);
                $this->assertNotSame('', $s['text']['ur']);
            }
        }
        $this->assertSame(['in', 'hold', 'out'], array_column($list['breathing_bubble']['steps'], 'cue'));
        $this->assertCount(10, $list['slow_count']['steps']);
    }

    public function test_finished_calm_activities_show_up_in_the_parents_report_and_stay_private(): void
    {
        $c = $this->parent();
        $this->postJson("/api/v1/children/{$c['id']}/activities/breathing_bubble/done", ['seconds' => 90])->assertCreated();
        $this->postJson("/api/v1/children/{$c['id']}/activities/breathing_bubble/done", ['seconds' => 30])->assertCreated();
        $this->postJson("/api/v1/children/{$c['id']}/activities/slow_count/done")->assertCreated();
        $this->postJson("/api/v1/children/{$c['id']}/activities/nonsense/done")->assertNotFound();
        $this->postJson("/api/v1/children/{$c['id']}/activities/slow_count/done", ['seconds' => 999999])->assertUnprocessable();

        $calm = $this->getJson("/api/v1/children/{$c['id']}/insights/summary")->assertOk()->json('calm');
        $this->assertSame(3, $calm['sessions']);
        $this->assertSame(2, $calm['minutes']);
        $this->assertSame(2, $calm['by_activity']['breathing_bubble']);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/children/{$c['id']}/activities/slow_count/done")->assertNotFound();
    }

    // ---------- new lesson task types ----------

    public function test_the_new_lesson_task_types_have_their_own_rules(): void
    {
        $this->parent();
        $meta = collect($this->getJson('/api/v1/task-types')->assertOk()->json())->keyBy('type');
        foreach (['trace_number', 'count_objects', 'say_it', 'social_story'] as $t) { $this->assertArrayHasKey($t, $meta); }
        $this->assertTrue($meta['count_objects']['choices']);
        $this->assertTrue($meta['social_story']['choices']);
        $this->assertTrue($meta['pick_picture']['choices']);
        $this->assertFalse($meta['trace_letter']['choices']);

        $child = Child::first();
        $lesson = $this->postJson("/api/v1/children/{$child->id}/lessons", ['title' => 'New kinds'])->json();
        $add = fn (array $t) => $this->postJson("/api/v1/lessons/{$lesson['id']}/tasks", $t)->assertCreated()->json('id');
        $a = $add(['type' => 'trace_number', 'title' => 'Seven', 'target' => '7']);
        $b = $add(['type' => 'trace_number', 'title' => 'Bad', 'target' => 'seven']);
        $c = $add(['type' => 'count_objects', 'title' => 'How many apples?']);
        $d = $add(['type' => 'say_it', 'title' => 'Ball', 'target' => 'ball']);
        $e = $add(['type' => 'social_story', 'title' => 'A friend takes your toy', 'instruction' => 'How do you feel?', 'options' => ['choices' => ['sad', 'happy'], 'correct' => 0]]);

        $problems = collect($this->getJson("/api/v1/lessons/{$lesson['id']}/readiness")->json('problems'))->keyBy('task_id');
        $this->assertArrayNotHasKey($a, $problems->all(), 'a good number is ready');
        $this->assertContains('digits', $problems[$b]['missing']);
        $this->assertEqualsCanonicalizing(['image', 'choices'], $problems[$c]['missing']);
        $this->assertSame(['image'], $problems[$d]['missing']);
        $this->assertArrayNotHasKey($e, $problems->all(), 'a social story needs only its choices');
    }
}
