<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    protected $fillable = ['child_id', 'relation_title', 'display_name', 'photo_path', 'audio_prompt_path', 'audio_mime'];

    protected $hidden = ['photo_path', 'audio_prompt_path'];   // storage paths never leave the server; clients get signed links

    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
}
