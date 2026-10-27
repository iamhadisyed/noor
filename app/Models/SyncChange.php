<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'entity', 'entity_id', 'op'];

    /** Remember that something changed for a parent, and nudge their other devices. */
    public static function record(?int $userId, string $entity, int $id, string $op = 'upsert'): void
    {
        if (! $userId) {
            return;   // platform templates belong to nobody
        }
        static::create(['user_id' => $userId, 'entity' => $entity, 'entity_id' => $id, 'op' => $op]);
    }
}
