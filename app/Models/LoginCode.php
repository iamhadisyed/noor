<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginCode extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['email', 'purpose', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'ip'];

    protected $casts = ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];

    /** Codes are stored only as a keyed hash, never in clear text. */
    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
