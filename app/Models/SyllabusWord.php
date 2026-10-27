<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyllabusWord extends Model
{
    public $timestamps = false;

    protected $fillable = ['syllabus_module_id', 'word', 'phonetic_hint', 'character_decomposition', 'audio_path', 'difficulty'];

    protected $casts = ['character_decomposition' => 'array'];
}
