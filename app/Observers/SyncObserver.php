<?php

namespace App\Observers;

use App\Jobs\SyncNudgeJob;
use App\Models\Assignment;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\AacCard;
use App\Models\FamilyMember;
use App\Models\Lesson;
use App\Models\LessonTask;
use App\Models\RewardLedger;
use App\Models\SyncChange;
use App\Services\Household;
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
        $users = Household::userIdsFor($m);
        foreach ($m->lessons()->pluck('id') as $id) { SyncChange::recordFor($users, 'lesson', $id, 'delete'); }
        foreach ($m->familyMembers()->pluck('id') as $id) { SyncChange::recordFor($users, 'family_member', $id, 'delete'); }
        foreach ($m->aacCards()->pluck('id') as $id) { SyncChange::recordFor($users, 'aac_card', $id, 'delete'); }
        foreach ($m->needsLogs()->pluck('id') as $id) { SyncChange::recordFor($users, 'need', $id, 'delete'); }
        foreach (Assignment::where('child_id', $m->id)->pluck('id') as $id) { SyncChange::recordFor($users, 'assignment', $id, 'delete'); }
    }

    private function record(Model $m, string $op): void
    {
        // Which child does this belong to? Everyone in that child's household gets the change.
        $child = match (true) {
            $m instanceof Child => $m,
            $m instanceof Lesson => $m->is_template ? null : $m->child,
            $m instanceof LessonTask => $m->lesson?->is_template ? null : $m->lesson?->child,
            $m instanceof FamilyMember, $m instanceof AacCard, $m instanceof ChildNeedsLog, $m instanceof RewardLedger, $m instanceof Assignment => $m->child,
            default => null,
        };
        [$entity, $id, $op] = match (true) {
            $m instanceof Child => ['child', $m->id, $op],
            $m instanceof Lesson => ['lesson', $m->id, $op],
            $m instanceof LessonTask => ['lesson', $m->lesson_id, 'upsert'],
            $m instanceof FamilyMember => ['family_member', $m->id, $op],
            $m instanceof AacCard => ['aac_card', $m->id, $op],
            $m instanceof ChildNeedsLog => ['need', $m->id, $op],
            $m instanceof RewardLedger => ['child', $m->child_id, 'upsert'],
            $m instanceof Assignment => ['assignment', $m->id, $op],
            default => ['', 0, $op],
        };
        if (! $child || ! $child->user_id) {
            return;
        }

        $users = Household::userIdsFor($child);
        SyncChange::recordFor($users, $entity, $id, $op);

        // One quiet "something changed" push per user every few seconds, however many rows moved.
        foreach ($users as $user) {
            if (Cache::add("sync:nudge:{$user}", 1, 5)) {
                SyncNudgeJob::dispatch($user)->afterResponse();
            }
        }
    }
}
