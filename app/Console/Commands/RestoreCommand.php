<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class RestoreCommand extends Command
{
    protected $signature = 'kindo:restore {file : Path to a backup zip} {--password= : Backup password (defaults to KINDO_BACKUP_PASSWORD)} {--force : Replace all current data without asking}';
    protected $description = 'Restore a backup. On a new server run `php artisan migrate` first. Replaces all current data.';

    public function handle(BackupService $backups): int
    {
        $password = $this->option('password') ?: config('kindo.backup.password');
        $file = $this->argument('file');
        if (! $this->option('force') && ! $this->confirm('This replaces ALL current data and pictures with the backup. Continue?')) {
            $this->warn('Nothing was changed.');

            return self::FAILURE;
        }
        try {
            $r = $backups->restore($file, $password);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Restored {$r['rows']} rows and {$r['files']} files. Everyone will need to sign in again.");

        return self::SUCCESS;
    }
}
