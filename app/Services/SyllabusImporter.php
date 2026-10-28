<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Turns whatever the vision model returned into tasks the lesson editor accepts.
 * The model is a suggestion engine: nothing it says is trusted, everything is clamped and filtered.
 */
class SyllabusImporter
{
    /** Types a photo can create. The others need a picture or a face, which a page of text does not give us. */
    public const TYPES = ['trace_letter', 'spell_word', 'listen_repeat'];

    public const MAX_TASKS = 12;

    /** @return array{title: string, tasks: list<array{type: string, title: string, instruction: string, target: string}>}|null */
    public function clean(?array $raw): ?array
    {
        if ($raw === null || ! is_array($raw['tasks'] ?? null)) {
            return null;
        }

        $seen = [];
        $tasks = [];
        foreach ($raw['tasks'] as $t) {
            if (! is_array($t) || ! in_array($t['type'] ?? null, self::TYPES, true)) {
                continue;
            }
            $target = trim(preg_replace('/\s+/u', ' ', (string) ($t['target'] ?? '')));
            $target = Str::limit($target, $t['type'] === 'trace_letter' ? 20 : 80, '');
            $key = $t['type'].'|'.mb_strtolower($target);
            if ($target === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $title = trim(strip_tags((string) ($t['title'] ?? '')));
            $tasks[] = [
                'type' => $t['type'],
                'title' => Str::limit($title !== '' ? $title : $target, 120, ''),
                'instruction' => Str::limit(trim(strip_tags((string) ($t['instruction'] ?? ''))), 400, ''),
                'target' => $target,
            ];
            if (count($tasks) >= self::MAX_TASKS) {
                break;
            }
        }

        if ($tasks === []) {
            return null;
        }

        $title = trim(strip_tags((string) ($raw['title'] ?? '')));

        return ['title' => Str::limit($title !== '' ? $title : 'Imported lesson', 150, ''), 'tasks' => $tasks];
    }
}
