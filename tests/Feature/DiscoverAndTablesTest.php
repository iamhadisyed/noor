<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GameEngine;
use App\Support\ChildKeyboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Discover (science facts and quizzes), the table and matching games, and full word coverage. */
class DiscoverAndTablesTest extends TestCase
{
    use RefreshDatabase;

    private const SCIENCE = ['science_animals', 'science_ocean', 'science_space', 'science_chemistry', 'science_ai'];
    private const TABLES = ['table_row', 'division_facts', 'case_match', 'number_word'];

    private function rounds(string $game, int $level = 3, string $lang = 'en', int $age = 6, int $n = 40): array
    {
        return (new GameEngine())->rounds($game, $level, $n, 3, [], $lang, $age);
    }

    public function test_every_topic_has_facts_for_each_age_in_both_languages(): void
    {
        foreach (config('learn.topics') as $topic => $t) {
            $this->assertNotEmpty($t['title']['ur'], $topic);
            $facts = config("learn.facts.{$topic}");
            $this->assertGreaterThanOrEqual(10, count($facts), $topic);
            foreach ($facts as $f) {
                [$key, $age, $emoji, $title, $text, $q, $right, $wrong] = $f;
                $this->assertNotSame('', $emoji);
                $this->assertGreaterThanOrEqual(2, count($wrong), "{$topic}/{$key} needs wrong answers");
                foreach ([$title, $text, $q, $right, ...$wrong] as $pair) {
                    $this->assertCount(2, $pair, "{$topic}/{$key}");
                    $this->assertNotSame('', trim($pair[0]));
                    $this->assertNotSame('', trim($pair[1]));
                }
            }
            // a child of any age 3 to 10 has something to read
            foreach (range(3, 10) as $age) {
                $fit = array_filter($facts, fn ($f) => $age >= $f[1][0] && $age <= $f[1][1]);
                $this->assertNotEmpty($fit, "{$topic} has nothing for age {$age}");
            }
        }
    }

    public function test_the_ai_topic_teaches_safety(): void
    {
        $text = strtolower(json_encode(config('learn.facts.ai')));
        $this->assertStringContainsString('wrong', $text);
        $this->assertStringContainsString('grown-up', $text);
    }

    public function test_learn_api_lists_topics_and_filters_by_age(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $topics = $this->getJson('/api/v1/learn')->assertOk()->json('topics');
        $this->assertSame(['animals', 'ocean', 'space', 'chemistry', 'ai'], array_column($topics, 'topic'));
        $this->assertSame('science_space', collect($topics)->firstWhere('topic', 'space')['game']);

        $young = $this->getJson('/api/v1/learn/space?age=4')->assertOk()->json('facts');
        $old = $this->getJson('/api/v1/learn/space?age=10')->assertOk()->json('facts');
        $this->assertNotEmpty($young);
        $this->assertNotEmpty($old);
        $this->assertNotSame(array_column($young, 'key'), array_column($old, 'key'));
        foreach ($young as $f) { $this->assertTrue(4 >= $f['age'][0] && 4 <= $f['age'][1]); }
        $this->getJson('/api/v1/learn/nope')->assertNotFound();
        $this->getJson('/api/v1/learn/space?age=abc')->assertStatus(422);
    }

    public function test_science_rounds_are_solvable_and_fit_the_age(): void
    {
        foreach (self::SCIENCE as $g) {
            foreach (['en', 'ur'] as $lang) {
                foreach ($this->rounds($g, 3, $lang, 4, 20) as $r) {
                    $this->assertSame($r['options'][$r['answer_index']], collect(config("learn.facts." . config("games.games.{$g}.topic")))->firstWhere(0, $r['learn']['key'])[6][$lang === 'ur' ? 1 : 0]);
                    $this->assertTrue(4 >= collect(config("learn.facts." . config("games.games.{$g}.topic")))->firstWhere(0, $r['learn']['key'])[1][0]);
                    $this->assertNotEmpty($r['learn']['text'][$lang]);
                    $this->assertCount(3, array_unique($r['options']));
                }
            }
        }
    }

    public function test_science_games_are_free_to_play_and_cost_no_minutes(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $child = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => now()->subYears(7)->toDateString()])->json();
        $this->postJson("/api/v1/children/{$child['id']}/games/count/start")->assertStatus(423);   // a normal game still needs minutes

        $start = $this->postJson("/api/v1/children/{$child['id']}/games/science_space/start", ['rounds' => 5])->assertCreated();
        $this->assertGreaterThan(0, $start->json('allowed_seconds'));
        foreach ($start->json('rounds') as $r) { $this->assertTrue(7 >= collect(config('learn.facts.space'))->firstWhere(0, $r['learn']['key'])[1][0]); }
        $done = $this->postJson('/api/v1/game-sessions/' . $start->json('session_id') . '/finish', ['rounds_played' => 5, 'correct' => 5, 'seconds_played' => 120])->assertOk();
        $this->assertSame(0, $done->json('minutes_spent'));
        $this->assertSame(0, $done->json('balance_minutes'));

        $list = collect($this->getJson("/api/v1/children/{$child['id']}/rewards")->json('games'));
        $this->assertTrue($list->firstWhere('game', 'science_ai')['unlocked']);
        $this->assertFalse($list->firstWhere('game', 'count')['unlocked']);
    }

    public function test_table_and_match_games_are_correct_and_typable(): void
    {
        foreach (self::TABLES as $g) {
            $this->assertNotNull(config("games.games.{$g}"), $g);
            foreach ([1, 3, 5] as $level) {
                foreach (['en', 'ur'] as $lang) {
                    foreach ($this->rounds($g, $level, $lang, 8, 30) as $r) {
                        $this->assertCount(3, array_unique($r['options']), "{$g} level {$level}");
                        $this->assertArrayHasKey($r['answer_index'], $r['options']);
                    }
                }
            }
        }
        foreach ($this->rounds('table_row', 4) as $r) {
            $cells = array_map('trim', explode(',', $r['expression']));
            $known = array_values(array_filter($cells, fn ($c) => $c !== '?'));
            $right = (int) $r['options'][$r['answer_index']];
            $hole = array_search('?', $cells);
            $this->assertEquals((int) $known[0] / (array_search($known[0], $cells) + 1) * ($hole + 1), $right);
        }
        foreach ($this->rounds('division_facts', 4) as $r) {
            [$a, $b] = array_map('intval', explode('÷', $r['expression']));
            $this->assertSame(intdiv($a, $b), (int) $r['options'][$r['answer_index']]);
            $this->assertSame(0, $a % $b);
        }
        foreach ($this->rounds('case_match', 4) as $r) {
            $this->assertSame(strtolower($r['expression']) === $r['expression'] ? strtoupper($r['expression']) : strtolower($r['expression']), $r['options'][$r['answer_index']]);
        }
    }

    public function test_number_words_match_the_numbers(): void
    {
        $en = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty'];
        foreach ($this->rounds('number_word', 5, 'en', 8, 60) as $r) {
            $right = $r['options'][$r['answer_index']];
            is_numeric($r['expression']) ? $this->assertSame($en[(int) $r['expression']], $right) : $this->assertSame(array_search($r['expression'], $en), $right);
        }
    }

    public function test_every_english_letter_has_a_picture_word_and_urdu_has_most(): void
    {
        $first = [];
        $ur = [];
        $icons = collect(config('games.icons'))->flatMap(fn ($c) => array_values($c))->all();
        foreach (array_merge($icons, array_map(fn ($w) => $w, array_values(config('games.words')))) as [$u, $e]) {
            $first[mb_substr(strtolower($e), 0, 1)] = true;
            if ($u) { $ur[mb_substr($u, 0, 1)] = true; $this->assertSame([], ChildKeyboard::unsupported($u), $u); }
            $this->assertSame([], ChildKeyboard::unsupported(strtolower($e)), $e);
        }
        $this->assertSame([], array_values(array_diff(ChildKeyboard::ENGLISH, array_keys($first))), 'a letter has no word');
        $this->assertGreaterThanOrEqual(25, count($ur));
    }

    public function test_first_letter_game_reaches_every_english_letter(): void
    {
        $seen = [];
        foreach ($this->rounds('first_letter', 1, 'en', 6, 1500) as $r) { $seen[$r['options'][$r['answer_index']]] = true; }
        $this->assertSame([], array_values(array_diff(ChildKeyboard::ENGLISH, array_keys($seen))));
    }

    public function test_picture_games_never_show_an_empty_urdu_word(): void
    {
        foreach (['word_to_picture', 'spell_picture', 'last_letter', 'first_letter'] as $g) {
            foreach ($this->rounds($g, 3, 'ur', 6, 60) as $r) {
                $this->assertNotSame('', $r['target'] ?? $r['word']['ur'] ?? 'x', $g);
                foreach ($r['options'] ?? [] as $o) {
                    if (is_array($o) && isset($o['name_ur'])) { $this->assertNotSame('', $o['name_ur'], $g); }
                }
            }
        }
    }
}
