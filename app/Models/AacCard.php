<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AacCard extends Model
{
    protected $fillable = ['child_id', 'category', 'english_label', 'urdu_label', 'icon_path', 'audio_path', 'is_emergency', 'is_pro'];

    protected $casts = ['is_emergency' => 'boolean', 'is_pro' => 'boolean'];
}
