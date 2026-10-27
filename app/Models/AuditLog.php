<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip'];

    protected $casts = ['meta' => 'array'];

    public static function record(?User $actor, string $action, ?Model $subject = null, array $meta = []): self
    {
        return static::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => Request::ip(),
        ]);
    }
}
