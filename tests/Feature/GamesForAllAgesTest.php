<?php

namespace Tests\Feature;

use App\Services\GameEngine;
use App\Support\ChildKeyboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The games added for ages 3 to 10: their rounds must be sound, solvable and typable. */
class GamesForAllAgesTest extends TestCase
{
    use RefreshDatabase;

    private const NEW = ['number_tap', 'size_game', 'word_to_picture', 'last_letter', 'bigger_number', 'number_bonds', 'word_blank', 'read_clock', 'scramble', 'hangman', 'times_table'];

    private function rounds(string $game, int $level, int $choices = 3, string $lang = 'en', int $n = 40): array
    {
        return (new GameEngine())->rounds($game, $level, $n, $choices, [], $lang);
    }

    public function test_every_new_game_is_in_the_config_with_an_age_range_and_a_title_in_both_languages(): void
    {
        foreach (self::NEW as $g) {
            $cfg = config("games.games.{$g}");
            $this->assertNotNull($cfg, $g);
            $this->assertNotEmpty($cfg['title']['en']);
            $this->assertNotEmpty($cfg['title']['ur']);
            $this->assertCount(2, $cfg['age'], $g);
            $this->assertGreaterThanOrEqual(3, $cfg['age'][0]);
            $this->assertLessThanOrEqual(10, $cfg['age'][1]);
        }
        // the range 3 to 10 is covered at every age
        foreach (range(3, 10) as $age) {
            $fit = array_filter(config('games.games'), fn ($g) => isset($g['age']) && $age >= $g['age'][0] && $age <= $g['age'][1]);
            $this->assertGreaterThanOrEqual(3, count($fit), "age {$age} has enough games");
        }
    }

    public function test_choice_games_have_the_right_number_of_distinct_options_and_a_valid_answer(): void
    {
        foreach (['number_tap', 'size_game', 'word_to_picture', 'last_letter', 'bigger_number', 'number_bonds', 'word_blank', 'read_clock', 'times_table'] as $game) {
            foreach (range(1, 5) as $level) {
                foreach ([2, 3, 4] as $choices) {
                    foreach (['en', 'ur'] as $lang) {
                        foreach ($this->rounds($game, $level, $choices, $lang, 12) as $r) {
                            $this->assertCount($choices, $r['options'], "{$game} L{$level} {$lang}");
                            $this->assertSame(count($r['options']), count(array_unique(array_map('json_encode', $r['options']))), "{$game} L{$level} duplicates");
                            $this->assertArrayHasKey($r['answer_index'], $r['options']);
                            $this->assertNotEmpty($r['prompt']['en']);
                            $this->assertNotEmpty($r['prompt']['ur']);
                        }
                    }
                }
            }
        }
    }

    public function test_the_numbers_and_clocks_are_right(): void
    {
        foreach (range(1, 5) as $level) {
            foreach ($this->rounds('number_tap', $level) as $r) {
                $this->assertSame($r['number'], $r['options'][$r['answer_index']]['count']);
            }
            foreach ($this->rounds('number_bonds', $level) as $r) {
                $this->assertSame($r['total'] - $r['a'], $r['options'][$r['answer_index']]);
                $this->assertGreaterThan(0, $r['total'] - $r['a']);
            }
            foreach ($this->rounds('times_table', $level) as $r) {
                $this->assertSame($r['a'] * $r['b'], $r['options'][$r['answer_index']]);
                $this->assertSame("{$r['a']} × {$r['b']}", $r['expression']);
            }
            foreach ($this->rounds('bigger_number', $level) as $r) {
                $opts = $r['options'];
                $this->assertSame($r['ask'] === 'bigger' ? max($opts) : min($opts), $opts[$r['answer_index']]);
            }
            foreach ($this->rounds('size_game', $level, 4) as $r) {
                $scales = array_column($r['options'], 'scale');
                $this->assertSame($r['ask'] === 'biggest' ? max($scales) : min($scales), $scales[$r['answer_index']]);
            }
            foreach ($this->rounds('read_clock', $level) as $r) {
                $this->assertSame(sprintf('%d:%02d', $r['hour'], $r['minute']), $r['options'][$r['answer_index']]);
                $this->assertGreaterThanOrEqual(1, $r['hour']);
                $this->assertLessThanOrEqual(12, $r['hour']);
                if ($level === 1) { $this->assertSame(0, $r['minute']); }
                if ($level === 2) { $this->assertContains($r['minute'], [0, 30]); }
                foreach ($r['options'] as $o) { $this->assertMatchesRegularExpression('/^(1[0-2]|[1-9]):[0-5]\d$/', $o); }
            }
        }
    }

    public function test_letter_games_use_real_letters_of_the_word(): void
    {
        foreach (['en', 'ur'] as $lang) {
            foreach (range(1, 5) as $level) {
                foreach ($this->rounds('last_letter', $level, 3, $lang) as $r) {
                    $name = $lang === 'ur' ? $r['icon']['name_ur'] : mb_strtolower($r['icon']['name_en']);
                    $this->assertSame(mb_substr($name, -1), $r['options'][$r['answer_index']]);
                }
                foreach ($this->rounds('word_blank', $level, 3, $lang) as $r) {
                    $holes = array_keys(array_filter($r['pattern'], fn ($l) => $l === null));
                    $this->assertCount(1, $holes, 'exactly one hole');
                    $word = $r['pattern'];
                    $word[$holes[0]] = $r['options'][$r['answer_index']];
                    $joined = implode('', $word);
                    $this->assertSame([], ChildKeyboard::unsupported($joined), 'the word can be typed on the keyboard');
                }
            }
        }
    }

    public function test_word_puzzles_can_be_solved_with_the_keyboard_and_are_really_jumbled(): void
    {
        foreach (['scramble', 'hangman'] as $game) {
            foreach (['en', 'ur'] as $lang) {
                foreach (range(1, 5) as $level) {
                    foreach ($this->rounds($game, $level, 3, $lang) as $r) {
                        $this->assertSame([], ChildKeyboard::unsupported($r['target']), "{$game} {$lang} {$r['target']}");
                        $this->assertSame($r['lang'] === 'ur' ? 'urdu' : 'english', $r['keyboard']);
                        $this->assertGreaterThanOrEqual(2, mb_strlen($r['target']));
                        if ($game === 'scramble') {
                            $letters = preg_split('//u', $r['target'], -1, PREG_SPLIT_NO_EMPTY);
                            $sorted = $r['letters'];
                            sort($sorted);
                            sort($letters);
                            $this->assertSame($letters, $sorted, 'the same letters, jumbled');
                            if (count(array_unique($letters)) > 1) {
                                $this->assertNotSame(implode('', $r['letters']), $r['target'], 'jumbled, not already solved');
                            }
                            $this->assertSame('scramble', $r['mode']);
                        } else {
                            $this->assertSame('guess', $r['mode']);
                            $this->assertGreaterThanOrEqual(6, $r['max_misses']);
                        }
                    }
                }
            }
        }
    }

    public function test_harder_levels_give_longer_words(): void
    {
        $avg = fn (int $level) => array_sum(array_map(fn ($r) => mb_strlen($r['target']), $this->rounds('scramble', $level, 3, 'en', 80))) / 80;
        $this->assertGreaterThan($avg(1), $avg(5));
    }

    public function test_the_word_list_matches_the_pictures_the_apps_can_draw(): void
    {
        // every word's picture key has to be known to the app, or the child sees a grey circle with a letter
        $art = file_get_contents(base_path('apps/kindo_ui/lib/src/widgets/art.dart'));
        $keys = array_merge(array_keys(config('games.words')), collect(config('games.icons'))->flatMap(fn ($c) => array_keys($c))->all());
        foreach ($keys as $k) {
            $this->assertMatchesRegularExpression("/'{$k}'\\s*:/", $art, "art.dart has no picture for '{$k}'");
        }
    }

    public function test_the_games_list_tells_the_apps_each_games_age(): void
    {
        Sanctum::actingAs(\App\Models\User::factory()->create());
        $list = $this->getJson('/api/v1/games')->assertOk()->json('games');
        $this->assertNotEmpty($list);
        $byGame = collect($list)->keyBy('game');
        $this->assertSame([3, 5], $byGame['number_tap']['age']);
        $this->assertSame([7, 10], $byGame['scramble']['age']);
        $this->assertSame([3, 6], $byGame['count']['age']);
        foreach ($byGame as $g) {
            $this->assertCount(2, $g['age'], 'every game says which ages it suits');
        }
    }
}
