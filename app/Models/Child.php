<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends Model
{
    protected $table = 'children';

    protected $fillable = ['user_id', 'name', 'date_of_birth', 'neurotype', 'track', 'support_profile', 'sensory_mode', 'avatar_id'];

    protected $casts = ['date_of_birth' => 'date', 'sensory_mode' => 'boolean', 'support_profile' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function familyMembers(): HasMany { return $this->hasMany(FamilyMember::class); }
    public function needsLogs(): HasMany { return $this->hasMany(ChildNeedsLog::class); }
    public function practiceSessions(): HasMany { return $this->hasMany(PracticeSession::class); }
    public function lessons(): HasMany { return $this->hasMany(Lesson::class); }
    public function syllabusModules(): HasMany { return $this->hasMany(SyllabusModule::class); }
    public function reports(): HasMany { return $this->hasMany(AiDiagnosticReport::class); }
}
