<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpgradeRequest extends Model
{
    protected $fillable = ['user_id', 'status', 'phone', 'note', 'payment_method', 'payment_reference', 'payment_note', 'paid_reported_at', 'days_granted', 'admin_note', 'handled_by', 'handled_at'];

    protected $casts = ['paid_reported_at' => 'datetime', 'handled_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Still waiting for the owner. */
    public function isOpen(): bool { return in_array($this->status, ['requested', 'paid_reported'], true); }
}
