<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * A portable backup that needs no mysqldump (shared hosting rarely has it): one zip with every table as
 * newline-delimited JSON, plus every picture and sound. It is encrypted with a password, because it holds
 * children's information. Sign-in tokens, codes and caches are left out: after a restore everyone signs in again.
 */
class BackupService
{
    public const DIR = 'backups';
    public const MEDIA_DIRS = ['lessons', 'family', 'games'];
    private const SKIP = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'personal_access_tokens', 'login_codes', 'idempotency_keys', 'sync_changes'];

    private function disk() { return Storage::disk('local'); }

    /** @return list<string> tables worth keeping */
    public function tables(): array
    {
        return collect(Schema::getTables())->pluck('name')->reject(fn ($t) => in_array($t, self::SKIP, true) || str_starts_with($t, 'sqlite_'))->values()->all();
    }

    /** @return array{file: string, tables: int, rows: int, files: int, bytes: int, encrypted: bool} */
    public function create(?string $password, ?string $name = null): array
    {
        $this->disk()->makeDirectory(self::DIR);
        $name ??= 'kindo-' . now()->format('Y-m-d-His') . '.zip';
        $path = $this->disk()->path(self::DIR . '/' . $name);
        @unlink($path);

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create the backup file.');
        }
        if ($password) {
            $zip->setPassword($password);
        }
        $add = function (string $entry, string $file) use ($zip, $password) {
            $zip->addFile($file, $entry);
            if ($password) {
                $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
            }
        };

        $tmp = [];
        $counts = [];
        $rows = 0;
        foreach ($this->tables() as $table) {
            $file = tempnam(sys_get_temp_dir(), 'kb');
            $tmp[] = $file;
            $h = fopen($file, 'w');
            $n = 0;
            foreach (DB::table($table)->cursor() as $row) {
                fwrite($h, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
                $n++;
            }
            fclose($h);
            $counts[$table] = $n;
            $rows += $n;
            $add("db/{$table}.ndjson", $file);
        }

        $files = [];
        foreach (self::MEDIA_DIRS as $dir) {
            foreach ($this->disk()->allFiles($dir) as $f) {
                $files[$f] = $this->disk()->size($f);
                $add("files/{$f}", $this->disk()->path($f));
            }
        }

        $manifest = ['app' => 'kindo', 'format' => 1, 'created_at' => now()->toIso8601String(), 'driver' => DB::getDriverName(), 'tables' => $counts, 'files' => $files,
            'migrations' => Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : []];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        if ($password) {
            $zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256);
        }
        $zip->close();
        foreach ($tmp as $t) { @unlink($t); }

        return ['file' => $path, 'name' => $name, 'tables' => count($counts), 'rows' => $rows, 'files' => count($files), 'bytes' => filesize($path), 'encrypted' => (bool) $password];
    }

    /** Opens a backup and reads its manifest. Throws a readable error if the password is wrong or the file is damaged. */
    private function open(string $file, ?string $password): array
    {
        $zip = new ZipArchive();
        if (! is_file($file) || $zip->open($file) !== true) {
            throw new \RuntimeException('That backup file cannot be opened.');
        }
        if ($password) {
            $zip->setPassword($password);
        }
        $raw = $zip->getFromName('manifest.json');
        $manifest = $raw ? json_decode($raw, true) : null;
        if (! is_array($manifest) || ($manifest['app'] ?? null) !== 'kindo') {
            $zip->close();
            throw new \RuntimeException($password ? 'Wrong password, or this is not a Kindo backup.' : 'This backup is encrypted: a password is needed.');
        }

        return [$zip, $manifest];
    }

    /** Re-reads every table and file and checks the counts and sizes. @return list<string> problems, empty when sound */
    public function verify(string $file, ?string $password): array
    {
        [$zip, $m] = $this->open($file, $password);
        $problems = [];
        foreach ($m['tables'] as $table => $expected) {
            $data = $zip->getFromName("db/{$table}.ndjson");
            if ($data === false) {
                $problems[] = "Table {$table} is missing.";
                continue;
            }
            $lines = $data === '' ? 0 : substr_count($data, "\n");
            if ($lines !== $expected) {
                $problems[] = "Table {$table} has {$lines} rows, expected {$expected}.";
            }
        }
        foreach ($m['files'] as $path => $size) {
            $stat = $zip->statName("files/{$path}");
            if (! $stat || $stat['size'] !== $size) {
                $problems[] = "File {$path} is missing or damaged.";
            }
        }
        $zip->close();

        return $problems;
    }

    /**
     * Put a backup back. The database must already have its tables (`php artisan migrate`). Existing data is replaced.
     * @return array{rows: int, files: int}
     */
    public function restore(string $file, ?string $password): array
    {
        [$zip, $m] = $this->open($file, $password);

        $known = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
        $missing = array_diff($m['migrations'], $known);
        if ($missing) {
            $zip->close();
            throw new \RuntimeException('This backup is from a newer version of Kindo. Update the code and run `php artisan migrate` first.');
        }
        if ($problems = $this->verifyOpen($zip, $m)) {
            $zip->close();
            throw new \RuntimeException('The backup is damaged: ' . $problems[0]);
        }

        $have = collect(Schema::getTables())->pluck('name')->all();
        $rows = 0;
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($zip, $m, $have, &$rows) {
                foreach ($this->tables() as $table) {
                    if ($table !== 'migrations') {                    // the new server's own schema record stays
                        DB::table($table)->delete();                  // replace, not merge
                    }
                }
                foreach (array_keys($m['tables']) as $table) {
                    if ($table === 'migrations' || ! in_array($table, $have, true)) {
                        continue;
                    }
                    $stream = $zip->getStream("db/{$table}.ndjson");
                    $batch = [];
                    while ($stream && ($line = fgets($stream)) !== false) {
                        $batch[] = json_decode($line, true);
                        if (count($batch) >= 200) {
                            DB::table($table)->insert($batch);
                            $rows += count($batch);
                            $batch = [];
                        }
                    }
                    if ($batch) {
                        DB::table($table)->insert($batch);
                        $rows += count($batch);
                    }
                    if ($stream) { fclose($stream); }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        foreach (self::MEDIA_DIRS as $dir) {
            $this->disk()->deleteDirectory($dir);
        }
        $n = 0;
        foreach (array_keys($m['files']) as $path) {
            $stream = $zip->getStream("files/{$path}");
            if ($stream) {
                $this->disk()->put($path, stream_get_contents($stream));
                fclose($stream);
                $n++;
            }
        }
        $zip->close();

        return ['rows' => $rows, 'files' => $n];
    }

    private function verifyOpen(ZipArchive $zip, array $m): array
    {
        $problems = [];
        foreach ($m['tables'] as $table => $expected) {
            $data = $zip->getFromName("db/{$table}.ndjson");
            if ($data === false || ($data === '' ? 0 : substr_count($data, "\n")) !== $expected) {
                $problems[] = "Table {$table} does not match its record.";
            }
        }
        foreach ($m['files'] as $path => $size) {
            if (! ($stat = $zip->statName("files/{$path}")) || $stat['size'] !== $size) {
                $problems[] = "File {$path} is missing or damaged.";
            }
        }

        return $problems;
    }

    /** Keeps the newest $keep backups. @return int removed */
    public function prune(int $keep): int
    {
        $files = collect($this->list())->sortByDesc('modified')->values();
        $old = $files->slice($keep);
        $old->each(fn ($f) => $this->disk()->delete(self::DIR . '/' . $f['name']));

        return $old->count();
    }

    /** @return list<array{name: string, bytes: int, modified: int}> */
    public function list(): array
    {
        if (! $this->disk()->exists(self::DIR)) {
            return [];
        }

        return collect($this->disk()->files(self::DIR))->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->map(fn ($f) => ['name' => basename($f), 'bytes' => $this->disk()->size($f), 'modified' => $this->disk()->lastModified($f)])->values()->all();
    }

    public function status(): array
    {
        $list = collect($this->list())->sortByDesc('modified');
        $latest = $list->first();

        return ['count' => $list->count(), 'last_at' => $latest ? date('c', $latest['modified']) : null, 'last_bytes' => $latest['bytes'] ?? null,
            'fresh' => $latest ? $latest['modified'] > now()->subHours(36)->timestamp : false, 'configured' => (bool) config('kindo.backup.password')];
    }
}
