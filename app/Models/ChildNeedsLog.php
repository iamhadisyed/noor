<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChildNeedsLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'card_id', 'need_type', 'acknowledged', 'acknowledged_at'];

    protected $casts = ['acknowledged' => 'boolean', 'acknowledged_at' => 'datetime'];

    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
}
