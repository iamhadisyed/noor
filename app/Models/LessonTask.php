<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonTask extends Model
{
    protected $fillable = ['lesson_id', 'position', 'type', 'title', 'instruction', 'target', 'options', 'needs_audio', 'image_path', 'image_alt', 'audio_path', 'audio_mime'];

    protected $casts = ['options' => 'array', 'needs_audio' => 'boolean'];

    public function lesson(): BelongsTo { return $this->belongsTo(Lesson::class); }
}
