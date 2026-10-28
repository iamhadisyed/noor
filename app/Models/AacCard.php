<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AacCard extends Model
{
    protected $fillable = ['child_id', 'category', 'english_label', 'urdu_label', 'icon_path', 'audio_path', 'audio_mime', 'is_emergency', 'is_pro'];

    protected $casts = ['is_emergency' => 'boolean', 'is_pro' => 'boolean'];

    /** Stock cards belong to the platform (no child); a parent's own cards belong to one child. */
    public function isCustom(): bool { return $this->child_id !== null; }

    public function child(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Child::class); }
}
