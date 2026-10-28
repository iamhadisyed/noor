<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIServiceManager;
use App\Services\ContentFilter;
use App\Services\MediaStore;
use App\Services\SyllabusImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonImportController extends Controller
{
    /** Photo of a syllabus page in, draft lesson out. The photo is read once and never stored. */
    public function store(Request $request, int $childId, MediaStore $media, AIServiceManager $ai, SyllabusImporter $importer): JsonResponse
    {
        $child = ChildController::owned($request, $childId);

        if (! $request->user()->hasProAccess()) {
            return response()->json(['message' => 'Importing from a photo needs Kindo Pro.', 'upgrade' => true], 402);
        }

        $data = $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:8192',
            'language' => 'sometimes|in:urdu,english,both',
            'week_number' => 'sometimes|integer|min:1|max:52',
        ]);
        $language = $data['language'] ?? 'both';

        try {
            $jpeg = $media->encodeJpeg($request->file('photo')->getRealPath(), 1600);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $outline = $importer->clean($ai->extractLessonFromImage($jpeg, $language));
        if ($outline === null) {
            return response()->json(['message' => 'We could not find a lesson on that photo. Try a clearer, well lit picture of one page, or add the lesson by hand.'], 422);
        }

        $lesson = DB::transaction(function () use ($child, $request, $outline, $data, $language) {
            $lesson = $child->lessons()->create([
                'user_id' => $request->user()->id, 'title' => $outline['title'], 'language' => $language, 'track' => $child->track,
                'week_number' => $data['week_number'] ?? 1, 'status' => 'draft',
                'description' => 'Imported from a photo. Check it, then add sounds and pictures where asked.',
            ]);
            foreach ($outline['tasks'] as $i => $t) {
                $lesson->tasks()->create($t + ['position' => $i + 1]);
            }

            return $lesson;
        });
        app(ContentFilter::class)->scanWholeLesson($lesson);

        return response()->json(app(LessonController::class)->present($lesson), 201);
    }
}
