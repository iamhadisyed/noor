<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSession extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'game', 'level', 'allowed_seconds', 'status', 'rounds_played', 'correct', 'seconds_played', 'minutes_spent', 'finished_at'];

    protected $casts = ['finished_at' => 'datetime'];

    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
}
