<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = ['code', 'days', 'note', 'expires_at', 'used_by', 'used_at', 'revoked_at', 'created_by'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class, 'used_by'); }

    public function status(): string
    {
        return match (true) {
            (bool) $this->used_at => 'used',
            (bool) $this->revoked_at => 'revoked',
            $this->expires_at && $this->expires_at->isPast() => 'expired',
            default => 'unused',
        };
    }

    /** K7M29QXP shown as K7M2-9QXP. */
    public function display(): string
    {
        return substr($this->code, 0, 4) . '-' . substr($this->code, 4);
    }
}
