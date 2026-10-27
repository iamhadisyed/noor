<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LessonTask;
use App\Services\MediaStore;
use Illuminate\Support\Facades\Storage;

/** Serves a lesson picture or sound. The signed, short-lived URL is the credential. */
class MediaController extends Controller
{
    public function show(int $task, string $kind)
    {
        abort_unless(in_array($kind, ['image', 'audio'], true), 404);
        $t = LessonTask::findOrFail($task);
        $path = $kind === 'image' ? $t->image_path : $t->audio_path;
        abort_unless($path && Storage::disk(MediaStore::DISK)->exists($path), 404);

        return Storage::disk(MediaStore::DISK)->response($path, null, [
            'Content-Type' => $kind === 'image' ? 'image/jpeg' : ($t->audio_mime ?: 'audio/mpeg'),
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
