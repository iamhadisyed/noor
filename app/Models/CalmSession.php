<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalmSession extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'activity', 'seconds'];
}
