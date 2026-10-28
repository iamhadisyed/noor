<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PracticeSession extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'module_id', 'language', 'total_attempts', 'successful_attempts', 'duration_seconds'];

    public function mistakes(): HasMany { return $this->hasMany(PracticeMistakeLog::class, 'session_id'); }

    public function letters(): HasMany { return $this->hasMany(SessionLetter::class, 'session_id'); }
}
