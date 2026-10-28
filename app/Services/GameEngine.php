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
}
