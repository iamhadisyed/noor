<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'source', 'days', 'starts_at', 'ends_at', 'voucher_id', 'note', 'created_by'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
