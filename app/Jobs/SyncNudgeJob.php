<?php

namespace App\Jobs;

use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

/** Tells a parent's phones to ask for changes. Carries no content: the phone fetches it with its own login. */
class SyncNudgeJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $userId) {}

    public function handle(FcmService $fcm): void
    {
        $fcm->sendData($this->userId, ['type' => 'sync']);
    }
}
