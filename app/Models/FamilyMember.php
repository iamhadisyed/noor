<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    protected $fillable = ['child_id', 'relation_title', 'display_name', 'photo_path', 'audio_prompt_path'];
}
