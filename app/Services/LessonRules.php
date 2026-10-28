<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\LessonTask;

/**
 * One place that says what each kind of task needs. The API enforces it, and
 * `GET /task-types` hands the same table to the web portal and the app.
 */
class LessonRules
{
    public const REQUIRED = 'required';
    public const OPTIONAL = 'optional';
    public const NONE = 'none';

    /** type => [label, image, audio, needs a target?, what the target is] */
    public const TYPES = [
        'trace_letter' => ['label' => 'Trace a letter',    'image' => self::OPTIONAL, 'audio' => self::OPTIONAL, 'target' => true,  'target_label' => 'Letter'],
        'spell_word'   => ['label' => 'Spell a word',      'image' => self::OPTIONAL, 'audio' => self::REQUIRED, 'target' => true,  'target_label' => 'Word'],
        'pick_picture' => ['label' => 'Pick the picture',  'image' => self::REQUIRED, 'audio' => self::OPTIONAL, 'target' => false, 'target_label' => null, 'choices' => true],
        'match_face'   => ['label' => 'Match a face',      'image' => self::REQUIRED, 'audio' => self::REQUIRED, 'target' => false, 'target_label' => null],
        'listen_repeat' => ['label' => 'Listen and repeat', 'image' => self::OPTIONAL, 'audio' => self::REQUIRED, 'target' => true,  'target_label' => 'Phrase'],
        'routine_step' => ['label' => 'Routine step',      'image' => self::REQUIRED, 'audio' => self::OPTIONAL, 'target' => false, 'target_label' => null],
        // added for a wider range of children: numbers, counting, naming things, and short social stories
        'trace_number' => ['label' => 'Trace a number',    'image' => self::OPTIONAL, 'audio' => self::OPTIONAL, 'target' => true,  'target_label' => 'Number'],
        'count_objects' => ['label' => 'Count the objects', 'image' => self::REQUIRED, 'audio' => self::OPTIONAL, 'target' => false, 'target_label' => null, 'choices' => true],
        'say_it'       => ['label' => 'Say what you see',  'image' => self::REQUIRED, 'audio' => self::OPTIONAL, 'target' => true,  'target_label' => 'The word'],
        'social_story' => ['label' => 'Social story question', 'image' => self::OPTIONAL, 'audio' => self::OPTIONAL, 'target' => false, 'target_label' => null, 'choices' => true],
    ];

    public static function types(): array
    {
        return collect(self::TYPES)->map(fn ($t, $k) => ['type' => $k] + $t + ['choices' => false])->values()->all();
    }

    public static function needsAudio(LessonTask $task): bool
    {
        return (self::TYPES[$task->type]['audio'] ?? self::NONE) === self::REQUIRED || $task->needs_audio;
    }

    public static function needsImage(LessonTask $task): bool
    {
        return (self::TYPES[$task->type]['image'] ?? self::NONE) === self::REQUIRED;
    }

    /**
     * Everything that stops a lesson from being published. Empty problems = ready.
     * Drafts are never checked, so a parent can always save half-finished work.
     */
    public static function readiness(Lesson $lesson): array
    {
        $lesson->loadMissing('tasks', 'child');
        $problems = [];

        if ($lesson->tasks->isEmpty()) {
            $problems[] = ['task_id' => null, 'position' => null, 'title' => null, 'missing' => ['tasks'], 'message' => 'Add at least one task.'];
        }

        $maxChoices = $lesson->settings['max_choices'] ?? $lesson->child?->support_profile['max_choices'] ?? 4;

        if ($lesson->flags()->where('status', 'open')->exists()) {
            $problems[] = ['task_id' => null, 'position' => null, 'title' => null, 'missing' => ['review'], 'message' => 'A moderator is reviewing this lesson. It can be published again after the review.'];
        }

        foreach ($lesson->tasks as $i => $task) {
            $rule = self::TYPES[$task->type] ?? null;
            $missing = [];

            if ($rule && $rule['target'] && trim((string) $task->target) === '') {
                $missing[] = 'target';
            }
            if ($task->type === 'spell_word' && trim((string) $task->target) !== '' && \App\Support\ChildKeyboard::unsupported($task->target) !== []) {
                $missing[] = 'keyboard_letters';   // the child types on an alphabet keyboard, so the word may only use its letters
            }
            if (self::needsImage($task) && ! $task->image_path) {
                $missing[] = 'image';
            }
            if (self::needsAudio($task) && ! $task->audio_path) {
                $missing[] = 'sound';
            }
            if ($task->type === 'trace_number' && trim((string) $task->target) !== '' && ! preg_match('/^[0-9]{1,3}$/', trim((string) $task->target))) {
                $missing[] = 'digits';   // a number to trace is one to three digits
            }
            if ($rule && ($rule['choices'] ?? false)) {
                $choices = $task->options['choices'] ?? [];
                if (count($choices) < 2) {
                    $missing[] = 'choices';
                } elseif (count($choices) > $maxChoices) {
                    $missing[] = 'fewer_choices';
                }
            }

            if ($missing) {
                $problems[] = [
                    'task_id' => $task->id, 'position' => $i + 1, 'title' => $task->title, 'missing' => $missing,
                    'message' => self::message($i + 1, $task->title, $missing, $maxChoices),
                ];
            }
        }

        return ['ready' => $problems === [], 'problems' => $problems];
    }

    private static function message(int $n, string $title, array $missing, int $maxChoices): string
    {
        $words = [
            'sound' => 'a sound', 'image' => 'a picture', 'target' => 'its letter or word',
            'keyboard_letters' => 'a word that uses only the letters on the child\'s keyboard (no digits, hyphens or other marks)', 'digits' => 'a number of one to three digits (0 to 9)', 'choices' => 'at least two answer choices', 'fewer_choices' => "no more than {$maxChoices} choices for this child",
        ];
        $parts = array_map(fn ($m) => $words[$m] ?? $m, $missing);
        $last = array_pop($parts);
        $list = $parts ? implode(', ', $parts) . ' and ' . $last : $last;

        return "Task {$n} \"{$title}\" needs {$list}.";
    }
}
