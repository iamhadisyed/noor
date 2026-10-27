<?php

namespace App\Console\Commands;

use App\Mail\ProExpiringMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BillingMaintenance extends Command
{
    protected $signature = 'kindo:billing-daily';
    protected $description = 'Warn people whose Pro ends soon, and move ended Pro plans back to free';

    public function handle(): int
    {
        // a warning three days ahead, once per expiry date
        $warned = 0;
        User::where('subscription_tier', 'pro')->whereNotNull('subscription_expires_at')->whereBetween('subscription_expires_at', [now(), now()->addDays(3)])
            ->where(fn ($q) => $q->whereNull('pro_reminder_for')->orWhere('pro_reminder_for', '!=', \DB::raw('date(subscription_expires_at)')))
            ->each(function (User $u) use (&$warned) {
                try {
                    Mail::to($u->email)->send(new ProExpiringMail($u->name ?: 'there', $u->subscription_expires_at->format('j F Y'), max(1, (int) ceil(now()->floatDiffInDays($u->subscription_expires_at)))));
                    $u->update(['pro_reminder_for' => $u->subscription_expires_at->toDateString()]);
                    $warned++;
                } catch (\Throwable $e) {
                    Log::error('Pro expiry email failed: ' . $e->getMessage());
                }
            });

        $ended = User::where('subscription_tier', 'pro')->whereNotNull('subscription_expires_at')->where('subscription_expires_at', '<', now())->update(['subscription_tier' => 'free']);
        $this->info("Warned {$warned}, ended {$ended}.");

        return self::SUCCESS;
    }
}
