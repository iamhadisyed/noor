<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Install extends Command
{
    protected $signature = 'kindo:install';
    protected $description = 'First setup: create the tables, the ready-made content, the owner admin account and the demo accounts';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);
        $this->call('db:seed', ['--force' => true]);
        $this->newLine();
        $this->info('Done. Sign in to the admin console at /portal with the admin email and PIN shown above.');
        $this->line('Next: fill in MAIL_*, APP_URL, FCM_* and the AI keys in .env (see README), then run `php artisan config:cache`.');

        return self::SUCCESS;
    }
}
