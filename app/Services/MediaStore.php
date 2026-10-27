<?php

namespace App\Services;

use App\Models\LessonTask;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Lesson media lives on the private disk and is served only through short-lived signed links.
 * Images are re-encoded (this strips metadata and anything hidden in the file); audio is checked by content, not by name.
 */
class MediaStore
{
    public const DISK = 'local';
    public const MAX_IMAGE_SIDE = 600;
    public const MAX_IMAGE_UPLOAD_KB = 2048;
    public const MAX_AUDIO_KB = 150;
    public const AUDIO_MIMES = ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/ogg', 'audio/webm', 'video/webm', 'audio/wav', 'audio/x-wav', 'audio/wave'];

    public function storeImage(LessonTask $task, UploadedFile $file): void
    {
        $src = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $src) {
            throw new \InvalidArgumentException('That file is not a picture we can read.');
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        $k = min(1, self::MAX_IMAGE_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $k));
        $nh = max(1, (int) round($h * $k));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // flatten transparency onto white
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        imagejpeg($dst, null, 82);
        $jpeg = (string) ob_get_clean();

        $this->forget($task->image_path);
        $path = $this->path($task, 'jpg');
        Storage::disk(self::DISK)->put($path, $jpeg);
        $task->update(['image_path' => $path]);
    }

    public function storeAudio(LessonTask $task, UploadedFile $file): void
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($mime, self::AUDIO_MIMES, true)) {
            throw new \InvalidArgumentException('That file is not a sound we can play.');
        }

        $this->forget($task->audio_path);
        $ext = ['audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac', 'audio/ogg' => 'ogg', 'audio/webm' => 'webm', 'video/webm' => 'webm'][$mime] ?? 'wav';
        $path = $this->path($task, $ext);
        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));
        $task->update(['audio_path' => $path, 'audio_mime' => $mime === 'video/webm' ? 'audio/webm' : $mime]);
    }

    public function remove(LessonTask $task, string $kind): void
    {
        $this->forget($kind === 'image' ? $task->image_path : $task->audio_path);
        $task->update($kind === 'image' ? ['image_path' => null] : ['audio_path' => null, 'audio_mime' => null]);
    }

    public function copy(LessonTask $from, LessonTask $to): void
    {
        foreach (['image' => 'image_path', 'audio' => 'audio_path'] as $kind => $col) {
            if ($from->$col && Storage::disk(self::DISK)->exists($from->$col)) {
                $path = $this->path($to, pathinfo($from->$col, PATHINFO_EXTENSION));
                Storage::disk(self::DISK)->copy($from->$col, $path);
                $to->$col = $path;
            }
        }
        $to->audio_mime = $from->audio_mime;
        $to->save();
    }

    public function forget(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function forgetTask(LessonTask $task): void
    {
        $this->forget($task->image_path);
        $this->forget($task->audio_path);
    }

    /** Short-lived link the browser or app can put straight into <img> or <audio>. */
    public function url(LessonTask $task, string $kind): ?string
    {
        $has = $kind === 'image' ? $task->image_path : $task->audio_path;

        return $has ? URL::temporarySignedRoute('media.show', now()->addMinutes(15), ['task' => $task->id, 'kind' => $kind]) : null;
    }

    private function path(LessonTask $task, string $ext): string
    {
        return "lessons/{$task->lesson_id}/" . Str::uuid() . '.' . $ext;
    }
}
