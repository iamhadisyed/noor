<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentFlag extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'task_id', 'family_member_id', 'reason', 'term', 'excerpt', 'fingerprint', 'status', 'action', 'note', 'reviewed_by', 'reviewed_at'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function lesson(): BelongsTo { return $this->belongsTo(Lesson::class); }
}
