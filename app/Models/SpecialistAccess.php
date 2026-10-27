<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialistAccess extends Model
{
    protected $table = 'specialist_access';

    protected $fillable = ['child_id', 'role', 'label', 'code_hash', 'code_expires_at', 'user_id', 'status', 'created_by', 'redeemed_at', 'last_viewed_at', 'revoked_at'];

    protected $hidden = ['code_hash'];

    protected $casts = ['code_expires_at' => 'datetime', 'redeemed_at' => 'datetime', 'last_viewed_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
