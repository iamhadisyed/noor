<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $fillable = ['child_id', 'created_by', 'title', 'note', 'due_on', 'status', 'done_at'];

    protected $casts = ['done_at' => 'datetime'];

    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
