<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameItem extends Model
{
    protected $fillable = ['kind', 'slug', 'name_en', 'name_ur', 'image_path', 'audio_path', 'audio_mime', 'active'];

    protected $hidden = ['image_path', 'audio_path'];

    protected $casts = ['active' => 'boolean'];

    /** A sound game can only use an animal that has both a picture and a sound. */
    public function isComplete(): bool
    {
        return $this->active && $this->image_path && $this->audio_path;
    }
}
