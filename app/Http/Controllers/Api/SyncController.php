<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildNeedsLog;
use App\Models\FamilyMember;
use App\Models\Lesson;
use App\Models\SyncChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The same changes, for every device. The app asks "what changed since cursor N?" and gets only that,
 * deletions included, so a phone on a weak connection catches up with the web portal in one small request.
 *
 *   GET /sync                 first call: everything the parent has, plus a cursor
 *   GET /sync?cursor=N        later calls: only what changed since N
 *
 * Entities: child, lesson (always whole, with its tasks and fresh picture/sound links), family_member, need.
 * A client applies changes in order; "delete" means remove it locally. Picture and sound links last 15
 * minutes, so download media when you sync and keep the files.
 */
class SyncController extends Controller
{
    private const PAGE = 300;
    private const NEEDS_DAYS = 30;

    public function __construct(private ChildController $children, private LessonController $lessons, private NeedsController $needs) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['cursor' => 'nullable|integer|min:0']);
        $user = $request->user();
        $cursor = $request->query('cursor');

        $latest = (int) SyncChange::where('user_id', $user->id)->max('id');
        $floor = (int) Cache::get('sync:floor', 0);
        $reset = $cursor !== null && (int) $cursor < $floor;      // older than what we still keep: start over
        if ($cursor === null || $reset) {
            return response()->json($this->snapshot($request, $latest) + ['reset' => $reset]);
        }

        $rows = SyncChange::where('user_id', $user->id)->where('id', '>', (int) $cursor)->orderBy('id')->limit(self::PAGE + 1)->get();
        $more = $rows->count() > self::PAGE;
        $rows = $rows->take(self::PAGE);

        // Keep only the last thing that happened to each item, in the order it last happened.
        $last = [];
        foreach ($rows as $r) {
            unset($last["{$r->entity}:{$r->entity_id}"]);
            $last["{$r->entity}:{$r->entity_id}"] = $r;
        }

        $changes = [];
        foreach ($last as $r) {
            $changes[] = $this->change($request, $r->entity, $r->entity_id, $r->op);
        }

        return response()->json([
            'snapshot' => false, 'reset' => false,
            'cursor' => $rows->isEmpty() ? (int) $cursor : (int) $rows->last()->id,
            'has_more' => $more,
            'changes' => array_values(array_filter($changes)),
        ]);
    }

    private function snapshot(Request $request, int $latest): array
    {
        $user = $request->user();
        $kids = $user->children()->get();
        $ids = $kids->pluck('id');
        $changes = [];

        foreach ($kids as $c) { $changes[] = ['entity' => 'child', 'id' => $c->id, 'op' => 'upsert', 'data' => $this->children->present($c)]; }
        foreach (Lesson::where('is_template', false)->whereIn('child_id', $ids)->orderBy('id')->get() as $l) { $changes[] = ['entity' => 'lesson', 'id' => $l->id, 'op' => 'upsert', 'data' => $this->lessons->present($l)]; }
        foreach (FamilyMember::whereIn('child_id', $ids)->orderBy('id')->get() as $m) { $changes[] = ['entity' => 'family_member', 'id' => $m->id, 'op' => 'upsert', 'data' => $m->toArray()]; }
        foreach (ChildNeedsLog::whereIn('child_id', $ids)->where('created_at', '>=', now()->subDays(self::NEEDS_DAYS))->orderBy('id')->get() as $n) { $changes[] = ['entity' => 'need', 'id' => $n->id, 'op' => 'upsert', 'data' => $this->needs->present($n)]; }

        return ['snapshot' => true, 'cursor' => $latest, 'has_more' => false, 'changes' => $changes];
    }

    /** The current state of one item, or a delete if it is gone or no longer this parent's. */
    private function change(Request $request, string $entity, int $id, string $op): ?array
    {
        $user = $request->user();
        $ids = $user->children()->select('id');
        $gone = ['entity' => $entity, 'id' => $id, 'op' => 'delete'];
        if ($op === 'delete') {
            return $gone;
        }

        $data = match ($entity) {
            'child' => ($c = $user->children()->find($id)) ? $this->children->present($c) : null,
            'lesson' => ($l = Lesson::where('is_template', false)->whereIn('child_id', $ids)->find($id)) ? $this->lessons->present($l) : null,
            'family_member' => ($m = FamilyMember::whereIn('child_id', $ids)->find($id)) ? $m->toArray() : null,
            'need' => ($n = ChildNeedsLog::whereIn('child_id', $ids)->find($id)) ? $this->needs->present($n) : null,
            default => null,
        };

        return $data === null ? $gone : ['entity' => $entity, 'id' => $id, 'op' => 'upsert', 'data' => $data];
    }
}
