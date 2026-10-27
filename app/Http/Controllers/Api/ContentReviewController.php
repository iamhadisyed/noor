<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BlockedTerm;
use App\Models\ContentFlag;
use App\Models\FamilyMember;
use App\Models\Lesson;
use App\Services\ContentFilter;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The super admin's view of what parents post for children. This is deliberately the one place where
 * an administrator sees names, pictures and sounds; every view of a lesson and every decision is audited.
 */
class ContentReviewController extends Controller
{
    public function __construct(private MediaStore $media, private LessonController $lessons) {}

    public function flags(Request $request): JsonResponse
    {
        $status = $request->query('status', 'open');
        $q = ContentFlag::query()->with('owner:id,email,name,status')->orderByDesc('id');
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        $page = $q->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn ($f) => $this->presentFlag($f))->all(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'open' => ContentFlag::where('status', 'open')->count()],
        ]);
    }

    /** Every parent-made lesson, newest first. Search by owner email or lesson title. */
    public function lessonsIndex(Request $request): JsonResponse
    {
        $q = Lesson::query()->where('is_template', false)->whereNotNull('user_id')->with(['child:id,name', 'tasks:id,lesson_id'])->withCount([
            'flags as open_flags' => fn ($w) => $w->where('status', 'open'),
        ])->orderByDesc('updated_at');

        if ($s = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhereIn('user_id', \App\Models\User::where('email', 'like', "%{$s}%")->select('id')));
        }
        if ($request->boolean('flagged')) {
            $q->whereHas('flags', fn ($w) => $w->where('status', 'open'));
        }
        $page = $q->paginate(25);
        $owners = \App\Models\User::whereIn('id', collect($page->items())->pluck('user_id'))->pluck('email', 'id');

        return response()->json([
            'data' => collect($page->items())->map(fn ($l) => [
                'id' => $l->id, 'title' => $l->title, 'status' => $l->status, 'child' => $l->child?->name, 'owner_email' => $owners[$l->user_id] ?? null,
                'tasks' => $l->tasks->count(), 'open_flags' => $l->open_flags, 'updated_at' => $l->updated_at,
            ])->all(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    /** Full lesson with signed picture and sound links, so the administrator can look and listen. */
    public function lessonShow(Request $request, int $id): JsonResponse
    {
        $lesson = Lesson::where('is_template', false)->whereNotNull('user_id')->with('child:id,name')->findOrFail($id);
        $owner = \App\Models\User::find($lesson->user_id);
        AuditLog::record($request->user(), 'admin.content_viewed', $lesson, ['owner_id' => $lesson->user_id]);

        return response()->json($this->lessons->present($lesson) + [
            'owner' => $owner ? ['id' => $owner->id, 'email' => $owner->email, 'status' => $owner->status] : null,
            'child_name' => $lesson->child?->name,
            'flags' => $lesson->flags()->latest('id')->get()->map(fn ($f) => $this->presentFlag($f))->all(),
        ]);
    }

    public function flagLesson(Request $request, int $id): JsonResponse
    {
        $lesson = Lesson::where('is_template', false)->whereNotNull('user_id')->findOrFail($id);
        $note = $request->validate(['note' => 'nullable|string|max:300'])['note'] ?? null;
        $flag = ContentFlag::create(['user_id' => $lesson->user_id, 'lesson_id' => $lesson->id, 'reason' => 'manual', 'note' => $note, 'excerpt' => mb_substr($lesson->title, 0, 200)]);
        if ($lesson->status === 'published') {
            $lesson->update(['status' => 'draft']);   // hidden from children while under review
        }
        AuditLog::record($request->user(), 'admin.content_flagged', $lesson, ['flag_id' => $flag->id]);

        return response()->json($this->presentFlag($flag), 201);
    }

    public function resolve(Request $request, int $id): JsonResponse
    {
        $flag = ContentFlag::where('status', 'open')->findOrFail($id);
        $data = $request->validate(['action' => 'required|in:dismiss,remove,deactivate_owner', 'note' => 'nullable|string|max:300']);
        $action = $data['action'];

        if ($action === 'remove') {
            $this->removeContent($flag);
        }
        if ($action === 'deactivate_owner') {
            $owner = \App\Models\User::findOrFail($flag->user_id);
            if ($owner->isSuperAdmin()) {
                return response()->json(['message' => 'An administrator cannot be made inactive here.'], 422);
            }
            $this->removeContent($flag);
            $owner->update(['status' => 'inactive', 'status_reason' => $data['note'] ?? 'Content review']);
            $owner->tokens()->delete();
        }

        $flag->update(['status' => $action === 'dismiss' ? 'dismissed' : 'actioned', 'action' => $action, 'note' => $data['note'] ?? $flag->note, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        AuditLog::record($request->user(), 'admin.flag_resolved', $flag, ['action' => $action, 'lesson_id' => $flag->lesson_id]);

        return response()->json($this->presentFlag($flag->fresh()));
    }

    public function terms(): JsonResponse
    {
        return response()->json(['custom' => BlockedTerm::orderBy('term')->get(['id', 'term']), 'built_in_count' => count(config('moderation.terms', []))]);
    }

    public function addTerm(Request $request): JsonResponse
    {
        $term = ContentFilter::plain($request->validate(['term' => 'required|string|min:2|max:60'])['term']);
        if (mb_strlen($term) < 2) {
            return response()->json(['message' => 'Enter a word or short phrase.', 'errors' => ['term' => ['Enter a word or short phrase.']]], 422);
        }
        $row = BlockedTerm::firstOrCreate(['term' => $term], ['created_by' => $request->user()->id]);
        AuditLog::record($request->user(), 'admin.term_added', $row, ['term' => $term]);

        return response()->json(['id' => $row->id, 'term' => $row->term], 201);
    }

    public function removeTerm(Request $request, int $id): JsonResponse
    {
        $row = BlockedTerm::findOrFail($id);
        $row->delete();
        AuditLog::record($request->user(), 'admin.term_removed', $row, ['term' => $row->term]);

        return response()->json(['ok' => true]);
    }

    private function removeContent(ContentFlag $flag): void
    {
        if ($flag->family_member_id && ($m = FamilyMember::find($flag->family_member_id))) {
            foreach ([$m->photo_path, $m->audio_prompt_path] as $path) {
                if ($path) {
                    Storage::disk('public')->delete($path);
                }
            }
            $m->delete();
        } elseif ($flag->lesson_id && ($lesson = Lesson::find($flag->lesson_id))) {
            $lesson->update(['status' => 'archived']);   // archived, not erased, so the decision can be reviewed
        }
    }

    private function presentFlag(ContentFlag $f): array
    {
        return [
            'id' => $f->id, 'reason' => $f->reason, 'term' => $f->term, 'excerpt' => $f->excerpt, 'status' => $f->status, 'action' => $f->action,
            'note' => $f->note, 'lesson_id' => $f->lesson_id, 'task_id' => $f->task_id, 'family_member_id' => $f->family_member_id,
            'owner' => $f->relationLoaded('owner') && $f->owner ? ['id' => $f->owner->id, 'email' => $f->owner->email, 'status' => $f->owner->status] : ['id' => $f->user_id],
            'created_at' => $f->created_at, 'reviewed_at' => $f->reviewed_at,
        ];
    }
}
