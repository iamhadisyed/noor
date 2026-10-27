<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
