<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'entity', 'entity_id', 'op'];

    /** The same change for every parent in a household. */
    public static function recordFor(array $userIds, string $entity, int $id, string $op = 'upsert'): void
    {
        foreach ($userIds as $userId) {
            static::record($userId, $entity, $id, $op);
        }
    }

    /** Tell one parent's devices to start over (their set of children changed). */
    public static function reset(int $userId): void
    {
        static::create(['user_id' => $userId, 'entity' => 'household', 'entity_id' => 0, 'op' => 'reset']);
    }

    /** Remember that something changed for a parent, and nudge their other devices. */
    public static function record(?int $userId, string $entity, int $id, string $op = 'upsert'): void
    {
        if (! $userId) {
            return;   // platform templates belong to nobody
        }
        static::create(['user_id' => $userId, 'entity' => $entity, 'entity_id' => $id, 'op' => $op]);
    }
}
