<?php

namespace App\Services;

/**
 * Builds game rounds. The correct answer is included on purpose: the app is offline-first and plays a whole
 * session without the network, then reports the score. Rewards are earned by practice, never by game scores,
 * so there is nothing to gain by peeking.
 *
 * Levels run 1 (easiest) to 5. `choices` is how many answers to offer, from the child's support profile.
 */
class GameEngine
{
    /** @param list<array> $animals complete library items: id, name_en, name_ur, image_url, audio_url */
    public function rounds(string $game, int $level, int $count, int $choices, array $animals = [], string $lang = 'en'): array
    {
        $level = max(1, min(config('games.max_level'), $level));
        $choices = max(2, min(4, $choices));
        $lang = $lang === 'ur' ? 'ur' : 'en';
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = ['n' => $i + 1] + match ($game) {
                'count' => $this->count($level, $choices),
                'add_sub' => $this->addSub($level, $choices),
                'number_order' => $this->numberOrder($level, $choices),
                'animal_sound' => $this->animalSound($choices, $animals),
                'sound_guess' => $this->soundGuess($choices, $animals),
                'memory_match' => $this->memory($level),
                'odd_one_out' => $this->oddOneOut($level, $choices),
                'pattern' => $this->pattern($level, $choices),
                'first_letter' => $this->firstLetter($level, $choices, $lang),
                'spell_picture' => $this->spellPicture($level, $lang),
                'alphabet_gap' => $this->alphabetGap($level, $choices, $lang),
                'opposites' => $this->opposites($level, $choices, $lang),
                'calendar' => $this->calendar($level, $choices),
                'colors' => $this->colors($level, $choices),
                'which_group' => $this->whichGroup($choices),
                'match_same' => $this->matchSame($level, $choices),
                'more_less' => $this->moreLess($level, $choices),
                'feelings' => $this->feelings($level, $choices),
                'routine_order' => $this->routineOrder($level),
                'number_tap' => $this->numberTap($level, $choices),
                'size_game' => $this->sizeGame($level, $choices),
                'word_to_picture' => $this->wordToPicture($choices, $lang),
                'last_letter' => $this->lastLetter($level, $choices, $lang),
                'bigger_number' => $this->biggerNumber($level, $choices),
                'number_bonds' => $this->numberBonds($level, $choices),
                'word_blank' => $this->wordBlank($level, $choices, $lang),
                'read_clock' => $this->readClock($level, $choices),
                'scramble' => $this->scramble($level, $lang),
                'hangman' => $this->hangman($level, $lang),
                'times_table' => $this->timesTable($level, $choices),
                default => throw new \InvalidArgumentException("Unknown game {$game}"),
            };
        }

        return $out;
    }

    /** Shuffle the right answer in among wrong ones. */
    private function pick(mixed $right, array $wrong, int $choices): array
    {
        $wrong = array_values(array_filter(array_unique($wrong, SORT_REGULAR), fn ($w) => $w !== $right));
        shuffle($wrong);
        $options = array_slice($wrong, 0, $choices - 1);
        $options[] = $right;
        shuffle($options);

        return [$options, array_search($right, $options, true)];
    }

    private function numbers(int $answer, int $max, int $choices): array
    {
        $near = array_filter(range(max(0, $answer - 3), min($max + 3, $answer + 3)), fn ($n) => $n !== $answer && $n >= 0);

        return $this->pick($answer, $near, $choices);
    }

    private function icon(?string $category = null): array
    {
        $icons = config('games.icons');
        $category ??= array_rand($icons);
        $key = array_rand($icons[$category]);

        return ['key' => $key, 'category' => $category, 'name_ur' => $icons[$category][$key][0], 'name_en' => $icons[$category][$key][1]];
    }

    private function count(int $level, int $choices): array
    {
        $max = [1 => 3, 2 => 5, 3 => 8, 4 => 10, 5 => 15][$level];
        $n = random_int(1, $max);
        [$options, $answer] = $this->numbers($n, $max, $choices);

        return ['prompt' => ['en' => 'How many?', 'ur' => 'کتنے ہیں؟'], 'icon' => $this->icon(), 'count' => $n, 'options' => $options, 'answer_index' => $answer];
    }

    private function addSub(int $level, int $choices): array
    {
        $limit = [1 => 5, 2 => 10, 3 => 10, 4 => 20, 5 => 20][$level];
        $subtract = $level >= 3 && random_int(0, 1) === 1;
        if ($subtract) {
            $a = random_int(1, $limit);
            $b = random_int(0, $a);
            $result = $a - $b;
        } else {
            $a = random_int(0, $limit - ($level === 1 ? 1 : 0));
            $b = random_int(0, $limit - $a);
            $result = $a + $b;
        }
        [$options, $answer] = $this->numbers($result, $limit, $choices);

        return ['prompt' => ['en' => 'What is the answer?', 'ur' => 'جواب کیا ہے؟'], 'expression' => $subtract ? "{$a} - {$b}" : "{$a} + {$b}", 'a' => $a, 'b' => $b, 'op' => $subtract ? '-' : '+', 'icon' => $this->icon(), 'options' => $options, 'answer_index' => $answer];
    }

    private function numberOrder(int $level, int $choices): array
    {
        $step = [1 => 1, 2 => 1, 3 => 2, 4 => 5, 5 => 10][$level];
        $descending = $level === 5 && random_int(0, 1) === 1;
        $start = $step === 1 ? random_int(1, $level === 1 ? 4 : 12) : $step * random_int(0, 4);
        $seq = [];
        for ($i = 0; $i < 5; $i++) {
            $seq[] = $descending ? $start + $step * (4 - $i) : $start + $step * $i;
        }
        $missing = random_int(1, 3);   // never the first or last, so there is always something to read from
        $right = $seq[$missing];
        $seq[$missing] = null;
        [$options, $answer] = $this->pick($right, [$right - $step, $right + $step, $right + 2 * $step, $right - 2 * $step, $right + 1, $right - 1], $choices);

        return ['prompt' => ['en' => 'What number is missing?', 'ur' => 'کون سا نمبر غائب ہے؟'], 'sequence' => $seq, 'missing_index' => $missing, 'options' => $options, 'answer_index' => $answer];
    }

    private function animalSound(int $choices, array $animals): array
    {
        $pool = $animals;
        shuffle($pool);
        $right = array_shift($pool);
        $wrong = array_slice($pool, 0, $choices - 1);
        $options = array_merge([$right], $wrong);
        shuffle($options);

        return ['prompt' => ['en' => 'Who makes this sound?', 'ur' => 'یہ آواز کون نکالتا ہے؟'], 'audio_url' => $right['audio_url'], 'item_id' => $right['id'],
            'options' => array_map(fn ($a) => ['item_id' => $a['id'], 'name_en' => $a['name_en'], 'name_ur' => $a['name_ur'], 'image_url' => $a['image_url']], $options),
            'answer_index' => array_search($right['id'], array_column($options, 'id'))];
    }

    /** The other way round: see the animal, pick its sound. */
    private function soundGuess(int $choices, array $animals): array
    {
        $pool = $animals;
        shuffle($pool);
        $right = array_shift($pool);
        $options = array_merge([$right], array_slice($pool, 0, $choices - 1));
        shuffle($options);

        return ['prompt' => ['en' => 'Which sound does this animal make?', 'ur' => 'یہ جانور کون سی آواز نکالتا ہے؟'], 'image_url' => $right['image_url'], 'name_en' => $right['name_en'], 'name_ur' => $right['name_ur'], 'item_id' => $right['id'],
            'options' => array_map(fn ($a) => ['item_id' => $a['id'], 'audio_url' => $a['audio_url']], $options),
            'answer_index' => array_search($right['id'], array_column($options, 'id'))];
    }

    /** One board of cards. The app deals and checks the flips; the server only chooses the pairs. */
    private function memory(int $level): array
    {
        $pairs = [1 => 2, 2 => 3, 3 => 4, 4 => 6, 5 => 8][$level];
        $all = [];
        foreach (config('games.icons') as $cat => $icons) {
            foreach ($icons as $key => [$ur, $en]) { $all[] = ['key' => $key, 'category' => $cat, 'name_en' => $en, 'name_ur' => $ur]; }
        }
        shuffle($all);

        return ['prompt' => ['en' => 'Find the pairs', 'ur' => 'جوڑے ڈھونڈو'], 'pairs' => array_slice($all, 0, $pairs), 'cards' => $pairs * 2];
    }

    private function oddOneOut(int $level, int $choices): array
    {
        $cats = array_keys(config('games.icons'));
        shuffle($cats);
        [$main, $other] = [$cats[0], $cats[1]];
        $keys = array_keys(config('games.icons')[$main]);
        shuffle($keys);
        $oddKey = array_rand(config('games.icons')[$other]);
        $same = array_slice($keys, 0, max(2, $choices - 1));
        $options = array_merge(array_map(fn ($k) => $this->named($main, $k), $same), [$this->named($other, $oddKey)]);
        shuffle($options);

        return ['prompt' => ['en' => 'Which one is different?', 'ur' => 'کون سا الگ ہے؟'], 'options' => $options, 'answer_index' => array_search($other, array_column($options, 'category'))];
    }

    private function named(string $category, string $key): array
    {
        [$ur, $en] = config('games.icons')[$category][$key];

        return ['key' => $key, 'category' => $category, 'name_en' => $en, 'name_ur' => $ur];
    }

    private function pattern(int $level, int $choices): array
    {
        $shapes = array_keys(config('games.icons.shape'));
        shuffle($shapes);
        [$a, $b, $c] = $shapes;
        $unit = [1 => [$a, $b], 2 => [$a, $a, $b], 3 => [$a, $b, $c], 4 => [$a, $b, $b], 5 => [$a, $b, $b, $c]][$level];
        $seq = [];
        for ($i = 0; $i < 8; $i++) { $seq[] = $unit[$i % count($unit)]; }
        $gap = random_int(count($unit) + 1, 7);                 // after at least one full repeat, so the pattern can be seen
        $right = $seq[$gap];
        $shown = $seq;
        $shown[$gap] = null;
        $shown = array_slice($shown, 0, $gap + 1);
        [$options, $answer] = $this->pick($right, array_values(array_diff($shapes, [$right])), $choices);

        return ['prompt' => ['en' => 'What comes next?', 'ur' => 'اگلا کون سا؟'], 'sequence' => array_map(fn ($k) => $k === null ? null : ['key' => $k], $shown),
            'options' => array_map(fn ($k) => ['key' => $k], $options), 'answer_index' => $answer];
    }

    // ---------- added games ----------

    /** Every picture the games can show, with its names. */
    private function allIcons(): array
    {
        $all = [];
        foreach (config('games.icons') as $cat => $icons) {
            foreach ($icons as $key => [$ur, $en]) { $all[] = ['key' => $key, 'category' => $cat, 'name_en' => $en, 'name_ur' => $ur]; }
        }

        return $all;
    }

    private function alphabet(string $lang): array
    {
        return $lang === 'ur' ? \App\Support\ChildKeyboard::URDU : \App\Support\ChildKeyboard::ENGLISH;
    }

    private function firstLetterOf(array $icon, string $lang): ?string
    {
        $name = $lang === 'ur' ? $icon['name_ur'] : mb_strtolower($icon['name_en']);
        $ch = mb_substr($name, 0, 1);

        return in_array($ch, $this->alphabet($lang), true) ? $ch : null;
    }

    private function firstLetter(int $level, int $choices, string $lang): array
    {
        $icons = array_values(array_filter($this->allIcons(), fn ($i) => $this->firstLetterOf($i, $lang) !== null));
        $icon = $icons[array_rand($icons)];
        $right = $this->firstLetterOf($icon, $lang);
        $abc = $this->alphabet($lang);
        $at = array_search($right, $abc, true);
        // easy levels offer letters from anywhere; hard ones offer neighbours that are easy to mix up
        $pool = $level <= 2 ? $abc : array_slice($abc, max(0, $at - 4), 9);
        [$options, $answer] = $this->pick($right, $pool, $choices);

        return ['prompt' => ['en' => 'Which letter does it start with?', 'ur' => 'یہ کس حرف سے شروع ہوتا ہے؟'], 'icon' => $icon, 'lang' => $lang, 'options' => $options, 'answer_index' => $answer];
    }

    /** See a picture, type its name on the alphabet keyboard. The app checks the typing against `target`. */
    private function spellPicture(int $level, string $lang): array
    {
        $max = [1 => 3, 2 => 4, 3 => 5, 4 => 6, 5 => 8][$level];
        $keys = $this->alphabet($lang);
        $words = [];
        foreach ($this->allIcons() as $icon) {
            $word = $lang === 'ur' ? $icon['name_ur'] : mb_strtolower($icon['name_en']);
            if (mb_strlen($word) <= $max && \App\Support\ChildKeyboard::unsupported($word) === [] && ! preg_match('/\s/u', $word)) {
                $words[] = [$icon, $word];
            }
        }
        if ($words === []) {   // no picture has a name this short in this language: fall back to the shortest English ones
            $lang = 'en';
            foreach ($this->allIcons() as $icon) {
                if (mb_strlen($icon['name_en']) <= 4) { $words[] = [$icon, mb_strtolower($icon['name_en'])]; }
            }
        }
        [$icon, $word] = $words[array_rand($words)];

        return ['prompt' => ['en' => 'Spell the name of this picture', 'ur' => 'اس تصویر کا نام ہجے کرو'], 'icon' => $icon, 'lang' => $lang, 'keyboard' => $lang === 'ur' ? 'urdu' : 'english',
            'target' => $word, 'letters_in_word' => mb_strlen($word), 'first_letter_hint' => $level <= 2];
    }

    private function alphabetGap(int $level, int $choices, string $lang): array
    {
        $abc = $this->alphabet($lang);
        $n = count($abc);
        $span = 5;
        $start = $level === 1 ? random_int(0, 4) : random_int(0, $n - $span);
        $descending = $level === 5 && random_int(0, 1) === 1;
        $seq = [];
        for ($i = 0; $i < $span; $i++) { $seq[] = $abc[$descending ? $start + $span - 1 - $i : $start + $i]; }
        $missing = random_int(1, 3);
        $right = $seq[$missing];
        $at = array_search($right, $abc, true);
        // wrong answers: the letters nearest the right one that are not already showing in the line
        $candidates = array_filter($abc, fn ($l) => ! in_array($l, $seq, true));
        uksort($candidates, fn ($x, $y) => abs($x - $at) <=> abs($y - $at));
        [$options, $answer] = $this->pick($right, array_slice(array_values($candidates), 0, 8), $choices);

        return ['prompt' => ['en' => 'Which letter is missing?', 'ur' => 'کون سا حرف غائب ہے؟'], 'lang' => $lang, 'sequence' => array_map(fn ($l, $i) => $i === $missing ? null : $l, $seq, array_keys($seq)),
            'missing_index' => $missing, 'options' => $options, 'answer_index' => $answer];
    }

    private function opposites(int $level, int $choices, string $lang): array
    {
        $pairs = config('games.opposites');
        $pool = $level <= 2 ? array_slice($pairs, 0, 8) : $pairs;
        $pair = $pool[array_rand($pool)];
        $flip = random_int(0, 1) === 1;
        [$word, $right] = $flip ? [[$pair[2], $pair[3]], [$pair[0], $pair[1]]] : [[$pair[0], $pair[1]], [$pair[2], $pair[3]]];
        $idx = $lang === 'ur' ? 1 : 0;
        $wrong = [];
        foreach ($pairs as $p) {
            if ($p[0] === $pair[0]) { continue; }
            foreach ([[$p[0], $p[1]], [$p[2], $p[3]]] as $w) { $wrong[] = $w[$idx]; }
        }
        [$options, $answer] = $this->pick($right[$idx], $wrong, $choices);

        return ['prompt' => ['en' => 'What is the opposite?', 'ur' => 'اس کا الٹ کیا ہے؟'], 'lang' => $lang, 'word' => ['en' => $word[0], 'ur' => $word[1]], 'options' => $options, 'answer_index' => $answer];
    }

    private function calendar(int $level, int $choices): array
    {
        $months = $level >= 4;
        $list = config($months ? 'games.months' : 'games.days');
        $n = count($list);
        $after = $level !== 3 && random_int(0, 5) > 0;   // mostly "after"; level 3 practises "before"
        $i = random_int(0, $n - 1);
        $rightIdx = ($i + ($after ? 1 : -1) + $n) % $n;
        $wrong = array_values(array_diff(array_keys($list), [$rightIdx, $i]));
        [$options, $answer] = $this->pick($rightIdx, $wrong, $choices);

        $kind = $months ? ['en' => 'month', 'ur' => 'مہینہ'] : ['en' => 'day', 'ur' => 'دن'];

        return ['prompt' => ['en' => "Which {$kind['en']} comes ".($after ? 'after' : 'before').' this one?', 'ur' => ($after ? 'اس کے بعد کون سا ' : 'اس سے پہلے کون سا ').$kind['ur'].' آتا ہے؟'],
            'kind' => $months ? 'month' : 'day', 'relation' => $after ? 'after' : 'before', 'given' => ['en' => $list[$i][0], 'ur' => $list[$i][1]],
            'options' => array_map(fn ($k) => ['en' => $list[$k][0], 'ur' => $list[$k][1]], $options), 'answer_index' => $answer];
    }

    private function colorItem(string $key): array
    {
        [$hex, $en, $ur] = config('games.colors')[$key];

        return ['key' => $key, 'hex' => $hex, 'name_en' => $en, 'name_ur' => $ur];
    }

    private function colors(int $level, int $choices): array
    {
        $pool = [1 => ['red', 'blue', 'green', 'yellow'], 2 => ['red', 'blue', 'green', 'yellow'], 3 => ['red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink']][$level] ?? array_keys(config('games.colors'));
        shuffle($pool);
        $right = array_shift($pool);
        $options = array_map(fn ($k) => $this->colorItem($k), array_merge([$right], array_slice($pool, 0, $choices - 1)));
        shuffle($options);
        $find = $level <= 2;   // first find the named colour, later name the colour you see

        return ['prompt' => $find ? ['en' => 'Tap the '.strtolower(config('games.colors')[$right][1]).' one', 'ur' => 'یہ رنگ چنو: '.config('games.colors')[$right][2]] : ['en' => 'What colour is this?', 'ur' => 'یہ کون سا رنگ ہے؟'],
            'mode' => $find ? 'find' : 'name', 'target' => $this->colorItem($right), 'options' => $options, 'answer_index' => array_search($right, array_column($options, 'key'))];
    }

    private function whichGroup(int $choices): array
    {
        $labels = ['fruit' => ['a fruit', 'پھل'], 'animal' => ['an animal', 'جانور'], 'vehicle' => ['a vehicle', 'سواری'], 'shape' => ['a shape', 'شکل'], 'toy' => ['a toy', 'کھلونا']];
        $cats = array_keys(config('games.icons'));
        shuffle($cats);
        $target = $cats[0];
        $options = [];
        foreach (array_slice($cats, 0, $choices) as $cat) {
            $key = array_rand(config('games.icons')[$cat]);
            $options[] = $this->named($cat, $key);
        }
        shuffle($options);

        return ['prompt' => ['en' => "Which one is {$labels[$target][0]}?", 'ur' => "کون سا {$labels[$target][1]} ہے؟"], 'group' => $target, 'options' => $options, 'answer_index' => array_search($target, array_column($options, 'category'))];
    }

    private function matchSame(int $level, int $choices): array
    {
        $all = $this->allIcons();
        $target = $all[array_rand($all)];
        $others = array_values(array_filter($all, fn ($i) => $i['key'] !== $target['key']));
        // easy levels: the other pictures are very different (other kinds of thing); hard levels: the same kind of thing
        $pool = array_values(array_filter($others, fn ($i) => ($i['category'] === $target['category']) === ($level >= 3)));
        if (count($pool) < $choices - 1) { $pool = $others; }
        shuffle($pool);
        $options = array_merge([$target], array_slice($pool, 0, $choices - 1));
        shuffle($options);

        return ['prompt' => ['en' => 'Find the one that is the same', 'ur' => 'ایک جیسا ڈھونڈو'], 'target' => $target, 'options' => $options, 'answer_index' => array_search($target['key'], array_column($options, 'key'))];
    }

    /** Several groups of the same picture: which has the most (or, from level 2, the fewest)? */
    private function moreLess(int $level, int $choices): array
    {
        $max = [1 => 5, 2 => 8, 3 => 10, 4 => 15, 5 => 20][$level];
        $gap = [1 => 3, 2 => 2, 3 => 2, 4 => 1, 5 => 1][$level];
        do {
            $counts = [];
            while (count($counts) < $choices) {
                $c = random_int(1, $max);
                if (! in_array($c, $counts, true)) { $counts[] = $c; }
            }
            $sorted = $counts;
            sort($sorted);
            $spaced = true;
            for ($i = 1; $i < count($sorted); $i++) { if ($sorted[$i] - $sorted[$i - 1] < min($gap, 2)) { $spaced = false; } }
        } while (! $spaced && $max >= $choices * 2);
        $fewest = $level >= 2 && random_int(0, 2) === 0;
        $icon = $this->icon();
        $options = array_map(fn ($c) => ['count' => $c, 'icon' => $icon], $counts);
        $right = $fewest ? min($counts) : max($counts);

        return ['prompt' => $fewest ? ['en' => 'Which group has the fewest?', 'ur' => 'کس گروپ میں سب سے کم ہیں؟'] : ['en' => 'Which group has the most?', 'ur' => 'کس گروپ میں سب سے زیادہ ہیں؟'],
            'ask' => $fewest ? 'fewest' : 'most', 'options' => $options, 'answer_index' => array_search($right, $counts, true)];
    }

    private function feelings(int $level, int $choices): array
    {
        $all = config('games.feelings');
        $keys = $level <= 2 ? ['happy', 'sad', 'angry', 'scared'] : array_keys($all);
        shuffle($keys);
        $right = array_shift($keys);
        $options = array_map(fn ($k) => ['key' => $k, 'name_en' => $all[$k][1], 'name_ur' => $all[$k][0]], array_merge([$right], array_slice($keys, 0, $choices - 1)));
        shuffle($options);
        $face = $level >= 3 && random_int(0, 1) === 1;   // later: given the word, find the face

        return ['prompt' => $face ? ['en' => 'Which face looks '.strtolower($all[$right][1]).'?', 'ur' => 'کون سا چہرہ '.$all[$right][0].' لگ رہا ہے؟'] : ['en' => 'How does this face feel?', 'ur' => 'یہ چہرہ کیسا محسوس کر رہا ہے؟'],
            'mode' => $face ? 'face' : 'name', 'target' => ['key' => $right, 'name_en' => $all[$right][1], 'name_ur' => $all[$right][0]], 'options' => $options, 'answer_index' => array_search($right, array_column($options, 'key'))];
    }

    /** "First ... then ...": put the steps of a daily routine in order. The app checks the order the child taps against `answer_order`. */
    private function routineOrder(int $level): array
    {
        $routines = config('games.routines');
        $key = array_rand($routines);
        $steps = $routines[$key]['steps'];
        $want = min([1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 5][$level], count($steps));
        $keys = array_keys($steps);
        $from = random_int(0, count($keys) - $want);
        $slice = array_slice($keys, $from, $want);
        $shuffled = $slice;
        do { shuffle($shuffled); } while ($shuffled === $slice);

        $named = fn ($k) => ['key' => $k, 'name_en' => $steps[$k][1], 'name_ur' => $steps[$k][0]];

        return ['prompt' => ['en' => 'Put these in order: what comes first?', 'ur' => 'ترتیب سے لگاؤ: پہلے کیا آتا ہے؟'], 'routine' => ['key' => $key, 'title' => $routines[$key]['title']],
            'steps' => array_map($named, $shuffled), 'answer_order' => $slice];
    }

    // ---------- games for ages 3 to 10 ----------

    /** "Show me 3": pick the group that has exactly that many. */
    private function numberTap(int $level, int $choices): array
    {
        $max = [1 => 3, 2 => 5, 3 => 7, 4 => 10, 5 => 12][$level];
        $n = random_int(1, $max);
        $icon = $this->icon();
        $others = array_values(array_filter(range(max(1, $n - 3), min($max + 2, $n + 3)), fn ($c) => $c !== $n));
        [$counts, $answer] = $this->pick($n, $others, $choices);

        return ['prompt' => ['en' => "Tap the group of {$n}", 'ur' => "{$n} والا گروپ چنو"], 'number' => $n,
            'options' => array_map(fn ($c) => ['count' => $c, 'icon' => $icon], $counts), 'answer_index' => $answer];
    }

    /** Biggest or smallest of the same picture drawn at different sizes. */
    private function sizeGame(int $level, int $choices): array
    {
        $scales = [0.4, 0.55, 0.7, 0.85, 1.0, 1.2];
        // easy levels: far apart sizes; hard levels: neighbours
        $step = min($level <= 2 ? 2 : 1, intdiv(count($scales) - 1, max(1, $choices - 1)));
        $start = random_int(0, count($scales) - 1 - $step * ($choices - 1));
        $set = [];
        for ($i = 0; $i < $choices; $i++) { $set[] = $scales[$start + $i * $step]; }
        $icon = $this->icon();
        $smallest = $level >= 2 && random_int(0, 2) === 0;
        $options = array_map(fn ($sc) => ['key' => $icon['key'], 'scale' => $sc], $set);
        shuffle($options);
        $target = $smallest ? min($set) : max($set);

        return ['prompt' => $smallest ? ['en' => 'Which one is the smallest?', 'ur' => 'سب سے چھوٹا کون سا ہے؟'] : ['en' => 'Which one is the biggest?', 'ur' => 'سب سے بڑا کون سا ہے؟'],
            'ask' => $smallest ? 'smallest' : 'biggest', 'options' => $options, 'answer_index' => array_search($target, array_column($options, 'scale'))];
    }

    /** A word to read; pick the picture. */
    private function wordToPicture(int $choices, string $lang): array
    {
        $all = $this->allIcons();
        $target = $all[array_rand($all)];
        $others = array_values(array_filter($all, fn ($i) => $i['key'] !== $target['key']));
        shuffle($others);
        $options = array_merge([$target], array_slice($others, 0, $choices - 1));
        shuffle($options);

        return ['prompt' => ['en' => 'Read the word. Which picture is it?', 'ur' => 'لفظ پڑھو۔ کون سی تصویر ہے؟'], 'lang' => $lang,
            'word' => ['en' => $target['name_en'], 'ur' => $target['name_ur']], 'options' => $options, 'answer_index' => array_search($target['key'], array_column($options, 'key'))];
    }

    private function lastLetter(int $level, int $choices, string $lang): array
    {
        $icons = array_values(array_filter($this->allIcons(), fn ($i) => $this->edgeLetter($i, $lang, false) !== null));
        $icon = $icons[array_rand($icons)];
        $right = $this->edgeLetter($icon, $lang, false);
        $abc = $this->alphabet($lang);
        $at = array_search($right, $abc, true);
        $pool = $level <= 2 ? $abc : array_slice($abc, max(0, $at - 4), 9);
        [$options, $answer] = $this->pick($right, $pool, $choices);

        return ['prompt' => ['en' => 'Which letter does it end with?', 'ur' => 'یہ کس حرف پر ختم ہوتا ہے؟'], 'icon' => $icon, 'lang' => $lang, 'options' => $options, 'answer_index' => $answer];
    }

    private function edgeLetter(array $icon, string $lang, bool $first): ?string
    {
        $name = $lang === 'ur' ? $icon['name_ur'] : mb_strtolower($icon['name_en']);
        $ch = $first ? mb_substr($name, 0, 1) : mb_substr($name, -1);

        return in_array($ch, $this->alphabet($lang), true) ? $ch : null;
    }

    private function biggerNumber(int $level, int $choices): array
    {
        $max = [1 => 10, 2 => 20, 3 => 99, 4 => 999, 5 => 9999][$level];
        $gap = max(1, (int) ($max / 12));
        $nums = [];
        $guard = 0;
        while (count($nums) < $choices && $guard++ < 200) {
            $c = random_int($level >= 3 ? 10 : 0, $max);
            $ok = true;
            foreach ($nums as $n) { if (abs($n - $c) < $gap) { $ok = false; } }
            if ($ok) { $nums[] = $c; }
        }
        while (count($nums) < $choices) { $nums[] = $nums[count($nums) - 1] + $gap + count($nums); }   // cannot happen at these sizes; keeps the options unique
        $smaller = $level >= 2 && random_int(0, 2) === 0;
        $right = $smaller ? min($nums) : max($nums);

        return ['prompt' => $smaller ? ['en' => 'Which number is smaller?', 'ur' => 'کون سا عدد چھوٹا ہے؟'] : ['en' => 'Which number is bigger?', 'ur' => 'کون سا عدد بڑا ہے؟'],
            'ask' => $smaller ? 'smaller' : 'bigger', 'options' => $nums, 'answer_index' => array_search($right, $nums, true)];
    }

    /** "3 + ? = 7". */
    private function numberBonds(int $level, int $choices): array
    {
        $total = random_int(...[1 => [3, 5], 2 => [5, 8], 3 => [7, 10], 4 => [10, 15], 5 => [12, 20]][$level]);
        $a = random_int(1, $total - 1);
        $b = $total - $a;
        $wrong = array_values(array_filter(range(0, $total + 1), fn ($n) => $n !== $b));
        usort($wrong, fn ($x, $y) => abs($x - $b) <=> abs($y - $b));
        [$options, $answer] = $this->pick($b, array_slice($wrong, 0, 6), $choices);

        return ['prompt' => ['en' => 'Find the missing part', 'ur' => 'غائب حصہ ڈھونڈو'], 'expression' => "{$a} + ? = {$total}", 'a' => $a, 'total' => $total, 'icon' => $this->icon(), 'options' => $options, 'answer_index' => $answer];
    }

    /** Words the word games can use, in the child's language, as [icon-like array, word]. */
    private function wordBank(string $lang, int $min, int $max): array
    {
        $bank = [];
        foreach ($this->allIcons() as $i) { $bank[$i['key']] = ['key' => $i['key'], 'name_en' => $i['name_en'], 'name_ur' => $i['name_ur']]; }
        foreach (config('games.words') as $key => [$ur, $en]) { $bank[$key] = ['key' => $key, 'name_en' => ucfirst($en), 'name_ur' => $ur ?? '']; }
        $out = [];
        foreach ($bank as $w) {
            $word = $lang === 'ur' ? $w['name_ur'] : mb_strtolower($w['name_en']);
            $len = mb_strlen($word);
            if ($word !== '' && $len >= $min && $len <= $max && ! preg_match('/\s/u', $word) && \App\Support\ChildKeyboard::unsupported($word) === []) {
                $out[] = [$w, $word];
            }
        }

        return $out;
    }

    /** Longest word that fits the level; widens the range if this language has none, so a round always exists. */
    private function pickWord(int $level, string $lang): array
    {
        [$min, $max] = $lang === 'ur' ? [1 => [2, 3], 2 => [3, 4], 3 => [3, 5], 4 => [4, 6], 5 => [4, 8]][$level] : [1 => [3, 4], 2 => [4, 5], 3 => [5, 6], 4 => [5, 7], 5 => [6, 8]][$level];
        $bank = $this->wordBank($lang, $min, $max);
        for ($w = 0; $bank === [] && $w < 6; $w++) { $bank = $this->wordBank($lang, max(2, $min - $w), $max + $w); }
        if ($bank === []) { $bank = $this->wordBank('en', 3, 5); $lang = 'en'; }
        [$icon, $word] = $bank[array_rand($bank)];

        return [$icon, $word, $lang];
    }

    /** A word with one hole. Pick the letter that fits. */
    private function wordBlank(int $level, int $choices, string $lang): array
    {
        [$icon, $word, $lang] = $this->pickWord(min(5, $level + 1), $lang);
        $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        $hole = random_int(0, count($letters) - 1);
        $right = $letters[$hole];
        $abc = $this->alphabet($lang);
        $at = array_search($right, $abc, true);
        $near = array_slice($abc, max(0, $at - 5), 11);
        $vowelsFirst = $lang === 'en' && in_array($right, ['a', 'e', 'i', 'o', 'u'], true);
        $pool = $vowelsFirst ? array_merge(['a', 'e', 'i', 'o', 'u'], $near) : array_merge($near, $abc);
        [$options, $answer] = $this->pick($right, $pool, $choices);
        $pattern = $letters;
        $pattern[$hole] = null;

        return ['prompt' => ['en' => 'Which letter is missing?', 'ur' => 'کون سا حرف غائب ہے؟'], 'icon' => $icon, 'lang' => $lang, 'pattern' => $pattern, 'options' => $options, 'answer_index' => $answer];
    }

    /** Read an analogue clock. The app draws the hands from `hour` and `minute`. */
    private function readClock(int $level, int $choices): array
    {
        $hour = random_int(1, 12);
        $minute = match ($level) { 1 => 0, 2 => [0, 30][random_int(0, 1)], 3 => [0, 15, 30, 45][random_int(0, 3)], default => random_int(0, 11) * 5 };
        $fmt = fn (int $h, int $m) => sprintf('%d:%02d', $h, $m);
        $right = $fmt($hour, $minute);
        $wrong = [];
        foreach ([[$hour % 12 + 1, $minute], [($hour + 10) % 12 + 1, $minute], [$hour, ($minute + 30) % 60], [$minute === 0 ? 12 : $minute / 5, $hour * 5 % 60], [$hour, ($minute + 15) % 60], [$hour % 12 + 1, ($minute + 30) % 60]] as [$h, $m]) {
            $h = (int) $h;
            $m = (int) $m;
            if ($level === 1) { $m = 0; }
            if ($level === 2) { $m = $m >= 30 ? 30 : 0; }
            $wrong[] = $fmt(max(1, min(12, $h)), $m);
        }
        $wrong = array_merge($wrong, [$fmt($hour % 12 + 1, 0), $fmt(($hour + 4) % 12 + 1, 0), $fmt(($hour + 7) % 12 + 1, 0), $fmt(($hour + 1) % 12 + 1, 30)]);
        [$options, $answer] = $this->pick($right, $wrong, $choices);

        return ['prompt' => ['en' => 'What time is it?', 'ur' => 'کیا وقت ہوا ہے؟'], 'hour' => $hour, 'minute' => $minute, 'options' => $options, 'answer_index' => $answer];
    }

    /** Jumbled letters to put in order (needs the alphabet keyboard's letters, so every word can be built). */
    private function scramble(int $level, string $lang): array
    {
        [$icon, $word, $lang] = $this->pickWord($level, $lang);
        $letters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        $shuffled = $letters;
        $tries = 0;
        do { shuffle($shuffled); } while ($shuffled === $letters && count(array_unique($letters)) > 1 && ++$tries < 30);

        return ['prompt' => ['en' => 'Put the letters in order', 'ur' => 'حروف کو ترتیب سے لگاؤ'], 'mode' => 'scramble', 'icon' => $icon, 'lang' => $lang, 'keyboard' => $lang === 'ur' ? 'urdu' : 'english',
            'target' => $word, 'letters' => $shuffled, 'show_hint' => $level <= 4];
    }

    /** Guess the secret word one letter at a time. A wrong guess costs a heart, but the word is always shown in the end: nobody "loses". */
    private function hangman(int $level, string $lang): array
    {
        [$icon, $word, $lang] = $this->pickWord($level, $lang);

        return ['prompt' => ['en' => 'Guess the secret word', 'ur' => 'پوشیدہ لفظ بوجھو'], 'mode' => 'guess', 'icon' => $icon, 'lang' => $lang, 'keyboard' => $lang === 'ur' ? 'urdu' : 'english',
            'target' => $word, 'letters_in_word' => mb_strlen($word), 'max_misses' => $level <= 2 ? 8 : 6, 'show_hint' => $level <= 3];
    }

    private function timesTable(int $level, int $choices): array
    {
        $tables = [1 => [2, 5, 10], 2 => [2, 3, 4, 5, 10], 3 => [2, 3, 4, 5, 6, 10], 4 => [2, 3, 4, 5, 6, 7, 8, 9], 5 => range(2, 12)][$level];
        $a = $tables[array_rand($tables)];
        $b = random_int(1, $level <= 2 ? 6 : ($level === 3 ? 8 : 10));
        $right = $a * $b;
        $wrong = array_values(array_filter([$right + $a, $right - $a, $right + 1, $right - 1, $right + 2, $right + 10, $a + $b, ($a + 1) * $b], fn ($n) => $n > 0 && $n !== $right));
        [$options, $answer] = $this->pick($right, $wrong, $choices);

        return ['prompt' => ['en' => 'How many altogether?', 'ur' => 'کل کتنے ہوئے؟'], 'expression' => "{$a} × {$b}", 'a' => $a, 'b' => $b, 'icon' => $this->icon(), 'options' => $options, 'answer_index' => $answer];
    }
}
