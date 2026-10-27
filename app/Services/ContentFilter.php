<?php

namespace App\Services;

use App\Models\BlockedTerm;
use App\Models\ContentFlag;
use App\Models\FamilyMember;
use App\Models\Lesson;
use App\Models\LessonTask;

/**
 * Watches parent-written text for words that do not belong in children's content.
 * It never blocks a save: it opens a flag for the super admin, and a lesson with an open flag
 * cannot be published (and a published one goes back to draft) until the flag is reviewed.
 */
class ContentFilter
{
    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's'];

    /** @return list<string> */
    public static function terms(): array
    {
        $all = array_merge(config('moderation.terms', []), BlockedTerm::pluck('term')->all());

        return array_values(array_unique(array_filter(array_map(fn ($t) => self::plain((string) $t), $all))));
    }

    /** A stored word as typed by the admin: lower case, letters and digits only. */
    public static function plain(string $term): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($term)) ?? '');
    }

    public static function normalise(string $text): string
    {
        $t = mb_strtolower($text);
        $t = preg_replace('/(?<=\p{L})!(?=\p{L})/u', 'i', $t) ?? $t;   // sh!t, but not "shit!"
        $t = strtr($t, self::LEET);
        $t = preg_replace_callback('/(?<![\p{L}\p{N}])\p{L}(?:[.\-_*]\p{L})+(?![\p{L}\p{N}])/u', fn ($m) => preg_replace('/[.\-_*]/', '', $m[0]), $t) ?? $t;   // f.u.c.k -> fuck
        $t = preg_replace('/(\p{L})\1{2,}/u', '$1', $t) ?? $t;                                        // fuuuck -> fuck
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? $t;

        return trim($t);
    }

    /** The first blocked term found in the text, or null. */
    public static function find(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $haystack = ' ' . self::normalise($text) . ' ';
        foreach (self::terms() as $term) {
            if (str_contains($haystack, ' ' . $term . ' ')) {
                return $term;
            }
        }

        return null;
    }

    public function scanLesson(Lesson $lesson): void
    {
        if ($lesson->is_template || ! $lesson->user_id) {
            return;
        }
        $this->check($lesson->user_id, $lesson->id, null, null, $lesson->title);
        $this->check($lesson->user_id, $lesson->id, null, null, $lesson->description);
    }

    public function scanWholeLesson(Lesson $lesson): void
    {
        $this->scanLesson($lesson);
        $lesson->tasks()->get()->each(fn ($t) => $this->scanTask($t));
    }

    public function scanTask(LessonTask $task): void
    {
        $lesson = $task->lesson;
        if (! $lesson || $lesson->is_template || ! $lesson->user_id) {
            return;
        }
        $texts = [$task->title, $task->instruction, $task->target, $task->image_alt, ...array_values((array) ($task->options['choices'] ?? []))];
        foreach ($texts as $text) {
            $this->check($lesson->user_id, $lesson->id, $task->id, null, is_string($text) ? $text : null);
        }
    }

    public function scanFamilyMember(FamilyMember $member, int $userId): void
    {
        $this->check($userId, null, null, $member->id, $member->display_name);
        $this->check($userId, null, null, $member->id, $member->relation_title);
    }

    private function check(int $userId, ?int $lessonId, ?int $taskId, ?int $familyId, ?string $text): void
    {
        $term = self::find($text);
        if (! $term) {
            return;
        }
        $fingerprint = sha1(implode('|', [$userId, $lessonId, $taskId, $familyId, $term, self::normalise((string) $text)]));
        if (ContentFlag::where('fingerprint', $fingerprint)->exists()) {
            return;   // already seen: open, or a moderator already ruled on this exact text
        }

        ContentFlag::create([
            'user_id' => $userId, 'lesson_id' => $lessonId, 'task_id' => $taskId, 'family_member_id' => $familyId,
            'reason' => 'bad_word', 'term' => $term, 'excerpt' => mb_substr((string) $text, 0, 200), 'fingerprint' => $fingerprint,
        ]);
    }
}
