<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyllabusModule extends Model
{
    protected $fillable = ['child_id', 'title', 'language', 'week_number', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function words(): HasMany { return $this->hasMany(SyllabusWord::class); }
}
