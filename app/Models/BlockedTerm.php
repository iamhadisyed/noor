<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockedTerm extends Model
{
    protected $fillable = ['term', 'created_by'];
}
