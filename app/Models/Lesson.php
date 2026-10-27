<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    protected $fillable = ['user_id', 'child_id', 'title', 'description', 'language', 'track', 'week_number', 'status', 'is_template', 'settings', 'published_at'];

    protected $casts = ['settings' => 'array', 'is_template' => 'boolean', 'published_at' => 'datetime'];

    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
    public function tasks(): HasMany { return $this->hasMany(LessonTask::class)->orderBy('position'); }
}
