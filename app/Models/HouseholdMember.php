<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    protected $fillable = ['owner_id', 'email', 'user_id', 'status', 'invited_by', 'accepted_at'];

    protected $casts = ['accepted_at' => 'datetime'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
