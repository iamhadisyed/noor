<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Weekly pediatric report for every child of an active Pro parent (processed by the cPanel cron queue worker).
Schedule::call(function () {
    \App\Models\Child::whereHas('user', fn ($q) => $q->where('subscription_tier', 'pro')
        ->where(fn ($q) => $q->whereNull('subscription_expires_at')->orWhere('subscription_expires_at', '>', now())))
        ->pluck('id')
        ->each(fn ($id) => \App\Jobs\GeneratePediatricReportJob::dispatch($id));
})->weeklyOn(0, '20:00')->name('weekly-pediatric-reports');

// Heartbeat so the admin health panel can tell the cron scheduler is alive.
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::put('scheduler:last_run', now()->toIso8601String(), 3600))->everyMinute()->name('scheduler-heartbeat');

// Keep the change feed and retry keys small. A phone that was offline longer than 30 days simply re-downloads everything.
Schedule::call(function () {
    $old = \App\Models\SyncChange::where('created_at', '<', now()->subDays(30));
    if ($max = $old->max('id')) {
        \Illuminate\Support\Facades\Cache::forever('sync:floor', max((int) \Illuminate\Support\Facades\Cache::get('sync:floor', 0), (int) $max));
        $old->delete();
    }
    \Illuminate\Support\Facades\DB::table('idempotency_keys')->where('created_at', '<', now()->subDays(2))->delete();
    \Illuminate\Support\Facades\DB::table('login_codes')->where('created_at', '<', now()->subDays(1))->delete();   // used or expired codes do not need keeping
})->dailyAt('03:10')->name('prune-sync');

// Pro expiry warnings (3 days ahead) and moving ended plans back to free.
Schedule::command('kindo:billing-daily')->dailyAt('04:05')->name('billing-daily');
// Nightly backup. Without KINDO_BACKUP_PASSWORD it does nothing and the admin health page shows no recent backup.
Schedule::command('kindo:backup')->dailyAt('02:30')->when(fn () => (bool) config('kindo.backup.password'))->name('nightly-backup')->onFailure(fn () => \Illuminate\Support\Facades\Log::error('Nightly backup failed'));

// Put the public demo accounts back to their starting state.
Schedule::command('kindo:demo-reset')->dailyAt('03:40')->when(fn () => (bool) config('kindo.demo.enabled'))->name('demo-reset');
