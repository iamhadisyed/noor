<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Removes a parent and everything of theirs: children, lessons, pictures, voices, devices, sessions. */
class AccountEraser
{
    public function __construct(private MediaStore $media) {}

    /** Files first (the database cannot delete them), then the rows. */
    public function eraseChild(\App\Models\Child $child): void
    {
        foreach ($child->lessons()->with('tasks')->get() as $lesson) {
            $lesson->tasks->each(fn ($t) => $this->media->forgetTask($t));
        }
        foreach ($child->familyMembers as $m) {
            $this->media->forgetFamily($m);
        }
        $child->delete();
    }

    public function erase(User $user): void
    {
        $id = $user->id;
        $email = $user->email;

        DB::transaction(function () use ($user) {
            foreach ($user->children()->get() as $child) {
                $this->eraseChild($child);
            }
            $user->tokens()->delete();
            LoginCode::where('email', $user->email)->delete();
            $user->delete();   // devices, lessons, flags, sync log and retry keys go with it
        });

        // The log keeps that it happened, without who: no email, no name.
        AuditLog::record(null, 'account.deleted', null, ['user_id' => $id, 'email_hash' => substr(hash('sha256', strtolower($email)), 0, 12)]);
    }
}
