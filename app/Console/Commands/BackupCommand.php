<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupCommand extends Command
{
    protected $signature = 'kindo:backup {--no-encrypt : Write an unencrypted backup (not recommended: it holds children\'s data)} {--copy-to= : Also copy the file to this folder, for example a synced drive}';
    protected $description = 'Back up the database and every picture and sound into one encrypted zip, and check it';

    public function handle(BackupService $backups): int
    {
        $password = config('kindo.backup.password');
        if (! $password && ! $this->option('no-encrypt')) {
            $this->error('Set KINDO_BACKUP_PASSWORD in .env first (backups hold children\'s information and are encrypted), or pass --no-encrypt.');

            return self::FAILURE;
        }
        $password = $this->option('no-encrypt') ? null : $password;

        $r = $backups->create($password);
        $problems = $backups->verify($r['file'], $password);
        if ($problems) {
            $this->error('The backup was written but failed its check: ' . implode(' ', array_slice($problems, 0, 3)));

            return self::FAILURE;
        }

        if ($dir = $this->option('copy-to')) {
            if (! is_dir($dir) || ! is_writable($dir) || ! copy($r['file'], rtrim($dir, '/') . '/' . $r['name'])) {
                $this->error("Could not copy the backup to {$dir}.");

                return self::FAILURE;
            }
        }
        $removed = $backups->prune((int) config('kindo.backup.keep'));
        $this->info(sprintf('Backup %s: %d tables, %d rows, %d files, %s%s. Checked OK.%s', $r['name'], $r['tables'], $r['rows'], $r['files'], number_format($r['bytes'] / 1048576, 2) . ' MB', $r['encrypted'] ? ', encrypted' : ', NOT encrypted', $removed ? " Removed {$removed} old backup(s)." : ''));

        return self::SUCCESS;
    }
}
