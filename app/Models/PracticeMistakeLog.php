<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticeMistakeLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['session_id', 'target_string', 'attempted_string', 'stroke_deviation_score', 'mistake_type'];
}
