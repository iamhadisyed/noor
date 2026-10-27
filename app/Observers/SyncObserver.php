<?php

namespace App\Observers;

use App\Jobs\SyncNudgeJob;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\FamilyMember;
use App\Models\Lesson;
use App\Models\LessonTask;
use App\Models\SyncChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Writes the change feed. A task is part of its lesson as far as a client is concerned, so a task change
 * is recorded as a change to the lesson (which is sent whole, tasks and links included).
 */
class SyncObserver
{
    public function saved(Model $m): void { $this->record($m, 'upsert'); }

    public function deleted(Model $m): void { $this->record($m, 'delete'); }

    /** Deleting a child removes everything under it in the database without model events, so list it here. */
    public function deleting(Model $m): void
    {
        if (! $m instanceof Child) {
            return;
        }
        foreach ($m->lessons()->pluck('id') as $id) { SyncChange::record($m->user_id, 'lesson', $id, 'delete'); }
        foreach ($m->familyMembers()->pluck('id') as $id) { SyncChange::record($m->user_id, 'family_member', $id, 'delete'); }
        foreach ($m->needsLogs()->pluck('id') as $id) { SyncChange::record($m->user_id, 'need', $id, 'delete'); }
    }

    private function record(Model $m, string $op): void
    {
        [$user, $entity, $id, $op] = match (true) {
            $m instanceof Child => [$m->user_id, 'child', $m->id, $op],
            $m instanceof Lesson => [$m->is_template ? null : $m->user_id, 'lesson', $m->id, $op],
            $m instanceof LessonTask => [$m->lesson?->is_template ? null : $m->lesson?->user_id, 'lesson', $m->lesson_id, 'upsert'],
            $m instanceof FamilyMember => [$m->child?->user_id, 'family_member', $m->id, $op],
            $m instanceof ChildNeedsLog => [$m->child?->user_id, 'need', $m->id, $op],
            default => [null, '', 0, $op],
        };
        if (! $user) {
            return;
        }
        SyncChange::record($user, $entity, $id, $op);

        // One quiet "something changed" push per user every few seconds, however many rows moved.
        if (Cache::add("sync:nudge:{$user}", 1, 5)) {
            SyncNudgeJob::dispatch($user)->afterResponse();
        }
    }
}
