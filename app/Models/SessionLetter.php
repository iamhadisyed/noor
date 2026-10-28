<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionLetter extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['session_id', 'child_id', 'language', 'letter', 'attempts', 'successes', 'assisted', 'deviation'];
}
