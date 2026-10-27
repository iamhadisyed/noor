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
    public function rounds(string $game, int $level, int $count, int $choices, array $animals = []): array
    {
        $level = max(1, min(config('games.max_level'), $level));
        $choices = max(2, min(4, $choices));
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
}
