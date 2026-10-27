<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameProgress extends Model
{
    protected $table = 'game_progress';

    protected $fillable = ['child_id', 'game', 'level', 'plays', 'last_accuracy'];
}
