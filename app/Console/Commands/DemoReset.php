<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class DemoReset extends Command
{
    protected $signature = 'kindo:demo-reset';
    protected $description = 'Put the two demo accounts back to their starting state (runs every night)';

    public function handle(): int
    {
        if (! config('kindo.demo.enabled')) {
            $this->info('Demo accounts are switched off.');

            return self::SUCCESS;
        }
        $this->laravel->make(DemoSeeder::class)->setContainer($this->laravel)->setCommand($this)->run();
        \App\Models\User::where('is_demo', true)->get()->each(fn ($u) => $u->tokens()->delete());

        return self::SUCCESS;
    }
}
