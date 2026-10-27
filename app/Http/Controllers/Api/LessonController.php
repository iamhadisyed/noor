<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\LessonTask;
use App\Services\LessonRules;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function __construct(private MediaStore $media) {}

    /** A lesson the signed-in parent owns, or 404. */
    public static function owned(Request $request, int $id): Lesson
    {
        return Lesson::where('is_template', false)->whereIn('child_id', $request->user()->children()->select('id'))->findOrFail($id);
    }

    public function present(Lesson $lesson): array
    {
        $lesson->load('tasks');
        $ready = LessonRules::readiness($lesson);

        return [
            'id' => $lesson->id, 'child_id' => $lesson->child_id, 'title' => $lesson->title, 'description' => $lesson->description,
            'language' => $lesson->language, 'track' => $lesson->track, 'week_number' => $lesson->week_number, 'status' => $lesson->status,
            'settings' => $lesson->settings, 'published_at' => $lesson->published_at, 'is_template' => $lesson->is_template,
            'ready' => $ready['ready'], 'problems' => $ready['problems'],
            'tasks' => $lesson->tasks->map(fn ($t) => TaskController::present($t, $this->media))->all(),
        ];
    }

    public function index(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        $q = $child->lessons()->where('status', '!=', 'archived')->with('tasks')->orderBy('week_number')->orderBy('id');
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }

        return response()->json($q->get()->map(fn ($l) => $this->present($l))->all());
    }

    public function store(Request $request, int $childId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        if ($blocked = $this->limit($request, $child)) {
            return $blocked;
        }

        $data = $request->validate($this->rules());
        $lesson = $child->lessons()->create($data + ['user_id' => $request->user()->id, 'track' => $data['track'] ?? $child->track]);

        return response()->json($this->present($lesson), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->present(self::owned($request, $id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $lesson = self::owned($request, $id);
        $lesson->update($request->validate($this->rules(true)));
        $this->revalidate($lesson);

        return response()->json($this->present($lesson->fresh()));
    }

    /** Archive, never hard delete: reports keep pointing at it. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        self::owned($request, $id)->update(['status' => 'archived']);

        return response()->json(['ok' => true]);
    }

    public function readiness(Request $request, int $id): JsonResponse
    {
        return response()->json(LessonRules::readiness(self::owned($request, $id)));
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $lesson = self::owned($request, $id);
        $check = LessonRules::readiness($lesson);

        if (! $check['ready']) {
            return response()->json(['message' => 'This lesson is not ready to publish yet.'] + $check, 422);
        }

        $lesson->update(['status' => 'published', 'published_at' => now()]);
        AuditLog::record($request->user(), 'lesson.published', $lesson);

        return response()->json($this->present($lesson->fresh()));
    }

    public function unpublish(Request $request, int $id): JsonResponse
    {
        $lesson = self::owned($request, $id);
        $lesson->update(['status' => 'draft']);

        return response()->json($this->present($lesson->fresh()));
    }

    /** Copy a lesson (with its pictures and sounds) to another child of the same parent. */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = self::owned($request, $id);
        $target = ChildController::owned($request, (int) $request->validate(['child_id' => 'required|integer'])['child_id']);
        if ($blocked = $this->limit($request, $target)) {
            return $blocked;
        }

        $copy = DB::transaction(function () use ($source, $target, $request) {
            $copy = $source->replicate(['status', 'published_at'])->fill([
                'child_id' => $target->id, 'user_id' => $request->user()->id, 'status' => 'draft', 'title' => $source->title . ' (copy)',
            ]);
            $copy->save();
            foreach ($source->tasks as $task) {
                $new = $task->replicate(['image_path', 'audio_path'])->fill(['lesson_id' => $copy->id]);
                $new->save();
                $this->media->copy($task, $new);
            }

            return $copy;
        });

        return response()->json($this->present($copy), 201);
    }

    public function templates(Request $request): JsonResponse
    {
        $q = Lesson::where('is_template', true)->whereNull('user_id')->with('tasks')->orderBy('track')->orderBy('id');
        if ($request->filled('track')) {
            $q->where('track', $request->query('track'));
        }

        return response()->json($q->get()->map(fn ($l) => [
            'id' => $l->id, 'title' => $l->title, 'description' => $l->description, 'language' => $l->language, 'track' => $l->track,
            'settings' => $l->settings, 'task_count' => $l->tasks->count(),
            'tasks' => $l->tasks->map(fn ($t) => ['type' => $t->type, 'title' => $t->title, 'target' => $t->target])->all(),
        ])->all());
    }

    public function fromTemplate(Request $request, int $childId, int $templateId): JsonResponse
    {
        $child = ChildController::owned($request, $childId);
        if ($blocked = $this->limit($request, $child)) {
            return $blocked;
        }
        $template = Lesson::where('is_template', true)->whereNull('user_id')->with('tasks')->findOrFail($templateId);

        $lesson = DB::transaction(function () use ($template, $child, $request) {
            $lesson = $template->replicate(['published_at'])->fill(['child_id' => $child->id, 'user_id' => $request->user()->id, 'is_template' => false, 'status' => 'draft']);
            $lesson->save();
            foreach ($template->tasks as $task) {
                $task->replicate(['image_path', 'audio_path', 'audio_mime'])->fill(['lesson_id' => $lesson->id])->save();
            }

            return $lesson;
        });

        return response()->json($this->present($lesson), 201);
    }

    public function taskTypes(): JsonResponse
    {
        return response()->json(LessonRules::types());
    }

    /** If an edit leaves a published lesson incomplete, pull it back to draft so a child never meets a broken task. */
    public static function revalidate(Lesson $lesson): bool
    {
        if ($lesson->status === 'published' && ! LessonRules::readiness($lesson->fresh())['ready']) {
            $lesson->update(['status' => 'draft']);

            return true;
        }

        return false;
    }

    private function limit(Request $request, $child): ?JsonResponse
    {
        $max = (int) config('kindo.free_limits.lessons_per_child');
        if (! $request->user()->isPro() && $child->lessons()->where('is_template', false)->where('status', '!=', 'archived')->count() >= $max) {
            return response()->json(['message' => "The free plan allows {$max} lessons per child.", 'upgrade' => true], 402);
        }

        return null;
    }

    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes|' : 'required|';

        return [
            'title' => $req . 'string|max:150',
            'description' => 'nullable|string|max:1000',
            'language' => ($partial ? 'sometimes|' : 'sometimes|') . 'in:urdu,english,both',
            'track' => 'sometimes|in:standard,special',
            'week_number' => 'sometimes|integer|min:1|max:52',
            'settings' => 'sometimes|nullable|array',
            'settings.magnet_px' => 'nullable|integer|between:15,40',
            'settings.pace' => 'nullable|in:slow,normal',
            'settings.max_choices' => 'nullable|integer|between:2,4',
            'settings.no_timers' => 'nullable|boolean',
            'settings.first_then' => 'nullable|boolean',
            'settings.praise' => 'nullable|in:quiet,lively',
        ];
    }
}
