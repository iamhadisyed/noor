<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonTask;
use App\Services\ContentFilter;
use App\Services\LessonRules;
use App\Services\MediaStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private MediaStore $media) {}

    public static function present(LessonTask $t, MediaStore $media): array
    {
        $rule = LessonRules::TYPES[$t->type] ?? null;

        return [
            'id' => $t->id, 'position' => $t->position, 'type' => $t->type, 'title' => $t->title, 'instruction' => $t->instruction,
            'target' => $t->target, 'keyboard' => $t->type === 'spell_word' ? \App\Support\ChildKeyboard::layoutFor($t->target) : null, 'options' => $t->options, 'needs_audio' => $t->needs_audio,
            'requires_sound' => LessonRules::needsAudio($t), 'requires_image' => LessonRules::needsImage($t),
            'has_image' => (bool) $t->image_path, 'has_sound' => (bool) $t->audio_path, 'image_alt' => $t->image_alt,
            'image_url' => $media->url($t, 'image'), 'audio_url' => $media->url($t, 'audio'), 'image_v' => MediaStore::version($t->image_path), 'audio_v' => MediaStore::version($t->audio_path),
        ];
    }

    private function ownedTask(Request $request, int $id): LessonTask
    {
        $task = LessonTask::findOrFail($id);
        LessonController::owned($request, $task->lesson_id);   // 404 unless the parent owns the lesson

        return $task;
    }

    private function taskRules(bool $partial = false): array
    {
        $r = $partial ? 'sometimes|' : 'required|';

        return [
            'type' => $r . Rule::in(array_keys(LessonRules::TYPES)),
            'title' => $r . 'string|max:120',
            'instruction' => 'nullable|string|max:400',
            'target' => 'nullable|string|max:80',
            'needs_audio' => 'sometimes|boolean',
            'image_alt' => 'nullable|string|max:160',
            'options' => 'sometimes|nullable|array',
            'options.choices' => 'required_with:options|array|min:2|max:4',
            'options.choices.*' => 'required|string|max:40',
            'options.correct' => 'required_with:options|integer|min:0|max:3',
        ];
    }

    public function store(Request $request, int $lessonId): JsonResponse
    {
        $lesson = LessonController::owned($request, $lessonId);
        $data = $request->validate($this->taskRules());
        $this->checkCorrect($data);

        $task = $lesson->tasks()->create($data + ['position' => ($lesson->tasks()->max('position') ?? 0) + 1])->refresh();   // refresh: pick up database defaults
        app(ContentFilter::class)->scanTask($task);

        return response()->json(self::present($task, $this->media) + ['lesson_unpublished' => LessonController::revalidate($lesson)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        $data = $request->validate($this->taskRules(true));
        $this->checkCorrect($data);
        $task->update($data);
        app(ContentFilter::class)->scanTask($task);

        return $this->done($task);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        $lesson = $task->lesson;
        $this->media->forgetTask($task);
        $task->delete();
        $lesson->tasks()->orderBy('position')->get()->each(fn ($t, $i) => $t->update(['position' => $i + 1]));

        return response()->json(['ok' => true, 'unpublished' => LessonController::revalidate($lesson)]);
    }

    public function reorder(Request $request, int $lessonId): JsonResponse
    {
        $lesson = LessonController::owned($request, $lessonId);
        $ids = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer'])['ids'];
        $mine = $lesson->tasks()->pluck('id')->all();
        if (count($ids) !== count($mine) || array_diff($ids, $mine)) {
            return response()->json(['message' => 'The list must contain every task of this lesson exactly once.'], 422);
        }
        DB::transaction(fn () => collect($ids)->each(fn ($id, $i) => LessonTask::findOrFail($id)->update(['position' => $i + 1])));

        return response()->json(['ok' => true]);
    }

    public function upload(Request $request, int $id): JsonResponse
    {
        $task = $this->ownedTask($request, $id);
        $kind = $request->validate(['kind' => 'required|in:image,audio'])['kind'];

        $request->validate($kind === 'image'
            ? ['file' => 'required|file|max:' . MediaStore::MAX_IMAGE_UPLOAD_KB]
            : ['file' => 'required|file|max:' . MediaStore::MAX_AUDIO_KB]);

        try {
            $kind === 'image' ? $this->media->storeImage($task, $request->file('file')) : $this->media->storeAudio($task, $request->file('file'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], 422);
        }

        return $this->done($task->fresh());
    }

    public function removeMedia(Request $request, int $id, string $kind): JsonResponse
    {
        abort_unless(in_array($kind, ['image', 'audio'], true), 404);
        $task = $this->ownedTask($request, $id);
        $this->media->remove($task, $kind);

        return $this->done($task->fresh());
    }

    private function done(LessonTask $task): JsonResponse
    {
        $unpublished = LessonController::revalidate($task->lesson);

        return response()->json(self::present($task, $this->media) + ['lesson_unpublished' => $unpublished]);
    }

    private function checkCorrect(array $data): void
    {
        if (isset($data['options']['choices'], $data['options']['correct']) && $data['options']['correct'] >= count($data['options']['choices'])) {
            abort(response()->json(['message' => 'The correct answer must be one of the choices.', 'errors' => ['options.correct' => ['Pick one of the choices.']]], 422));
        }
    }
}
