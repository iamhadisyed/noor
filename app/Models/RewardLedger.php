<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardLedger extends Model
{
    protected $table = 'reward_ledger';

    public const UPDATED_AT = null;

    protected $fillable = ['child_id', 'minutes', 'reason', 'ref_id', 'note', 'created_by'];

    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
}
