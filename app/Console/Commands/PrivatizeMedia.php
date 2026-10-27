<?php

namespace App\Console\Commands;

use App\Models\FamilyMember;
use App\Services\MediaStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** One-time move of family-card photos and voices from the public disk to private storage. Safe to run again. */
class PrivatizeMedia extends Command
{
    protected $signature = 'kindo:privatize-media';
    protected $description = 'Move family card photos and voices from public to private storage';

    public function handle(MediaStore $media): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(MediaStore::DISK);
        $moved = 0;

        foreach (FamilyMember::cursor() as $m) {
            foreach (['photo_path' => 'photo', 'audio_prompt_path' => 'audio'] as $col => $kind) {
                $path = $m->$col;
                if (! $path || ! $public->exists($path)) {
                    continue;
                }
                $bytes = $public->get($path);
                if ($kind === 'photo') {
                    $tmp = tempnam(sys_get_temp_dir(), 'fm');
                    file_put_contents($tmp, $bytes);
                    try { $bytes = $media->encodeJpeg($tmp); } catch (\InvalidArgumentException) { /* keep the original bytes */ } finally { @unlink($tmp); }
                }
                $private->put($path, $bytes);
                if ($kind === 'audio' && ! $m->audio_mime) {
                    $m->audio_mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'audio/mpeg';
                    $m->audio_mime = ['video/webm' => 'audio/webm', 'video/mp4' => 'audio/mp4'][$m->audio_mime] ?? $m->audio_mime;
                }
                $public->delete($path);
                $moved++;
            }
            $m->save();
        }

        $this->info("Moved {$moved} file(s) to private storage.");

        return self::SUCCESS;
    }
}
