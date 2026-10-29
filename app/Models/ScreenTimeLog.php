<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreenTimeLog extends Model
{
    protected $fillable = ['child_id', 'day', 'seconds', 'bonus_seconds'];

    protected $casts = ['day' => 'date:Y-m-d'];
}
