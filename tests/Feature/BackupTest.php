<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\FamilyMember;
use App\Models\GameItem;
use App\Models\Lesson;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// DatabaseMigrations (not RefreshDatabase): a restore switches foreign-key checks off, which a wrapping transaction would block.
class BackupTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        config(['kindo.backup.password' => 'correct horse battery staple', 'kindo.backup.keep' => 3]);
    }

    /** A small family: a parent, a child, a lesson with a picture and a sound, a family card, a game animal and a sign-in token. */
    private function family(): array
    {
        $u = User::factory()->create(['email' => 'mama@example.com', 'name' => 'Mama Ünï', 'subscription_tier' => 'pro']);
        $c = $u->children()->create(['name' => 'Ayan', 'date_of_birth' => '2020-01-01']);
        $l = Lesson::create(['user_id' => $u->id, 'child_id' => $c->id, 'title' => 'الف ب', 'track' => 'standard']);
        $t = $l->tasks()->create(['type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا', 'position' => 1, 'image_path' => "lessons/{$l->id}/a.jpg", 'audio_path' => "lessons/{$l->id}/a.wav", 'audio_mime' => 'audio/wav']);
        Storage::disk('local')->put($t->image_path, 'JPEGBYTES-' . str_repeat('x', 500));
        Storage::disk('local')->put($t->audio_path, 'WAVBYTES-' . str_repeat('y', 500));
        $f = FamilyMember::create(['child_id' => $c->id, 'relation_title' => 'Mama', 'display_name' => 'Ammi', 'photo_path' => "family/{$c->id}/p.jpg", 'audio_prompt_path' => "family/{$c->id}/v.wav"]);
        Storage::disk('local')->put($f->photo_path, 'PHOTO');
        Storage::disk('local')->put($f->audio_prompt_path, 'VOICE');
        GameItem::create(['slug' => 'lion', 'name_en' => 'Lion', 'name_ur' => 'شیر']);
        $u->createToken('phone');

        return [$u, $c, $l, $t, $f];
    }

    private function wipe(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        foreach (app(BackupService::class)->tables() as $t) { if ($t !== 'migrations') { DB::table($t)->delete(); } }
        DB::table('personal_access_tokens')->delete();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        Storage::disk('local')->deleteDirectory('lessons');
        Storage::disk('local')->deleteDirectory('family');
    }

    // ---------- making a backup ----------

    public function test_a_backup_is_encrypted_checked_and_leaves_out_sign_in_secrets(): void
    {
        $this->family();
        $this->artisan('kindo:backup')->expectsOutputToContain('encrypted. Checked OK')->assertSuccessful();

        $file = Storage::disk('local')->path('backups/' . basename(Storage::disk('local')->files('backups')[0]));
        $svc = app(BackupService::class);
        $this->assertSame([], $svc->verify($file, 'correct horse battery staple'));

        // without the password it cannot be read, and the data is not sitting in the file in the clear
        $this->expectException(\RuntimeException::class);
        try {
            $svc->verify($file, null);
        } finally {
            $raw = file_get_contents($file);
            $this->assertStringNotContainsString('mama@example.com', $raw);
            $this->assertStringNotContainsString('Ayan', $raw);
        }
    }

    public function test_a_wrong_password_is_refused_with_a_clear_message(): void
    {
        $this->family();
        $r = app(BackupService::class)->create('right-password');
        $this->expectExceptionMessage('Wrong password');
        app(BackupService::class)->verify($r['file'], 'wrong-password');
    }

    public function test_tokens_codes_and_caches_are_not_in_the_backup(): void
    {
        $this->family();
        DB::table('login_codes')->insert(['email' => 'x@example.com', 'code_hash' => 'h', 'expires_at' => now(), 'created_at' => now()]);
        $tables = app(BackupService::class)->tables();
        foreach (['personal_access_tokens', 'login_codes', 'sessions', 'cache', 'jobs', 'idempotency_keys', 'sync_changes'] as $skip) {
            $this->assertNotContains($skip, $tables);
        }
        foreach (['users', 'children', 'lessons', 'lesson_tasks', 'family_members', 'game_items', 'migrations'] as $keep) {
            $this->assertContains($keep, $tables);
        }
    }

    public function test_it_refuses_to_run_without_a_password_unless_told_to(): void
    {
        config(['kindo.backup.password' => null]);
        $this->artisan('kindo:backup')->expectsOutputToContain('KINDO_BACKUP_PASSWORD')->assertFailed();
        $this->assertSame([], Storage::disk('local')->files('backups'));

        $this->artisan('kindo:backup', ['--no-encrypt' => true])->expectsOutputToContain('NOT encrypted')->assertSuccessful();
        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }

    public function test_only_the_newest_backups_are_kept(): void
    {
        $svc = app(BackupService::class);
        foreach (range(1, 5) as $i) {
            $svc->create('pw', "kindo-2026-01-0{$i}.zip");
            touch(Storage::disk('local')->path("backups/kindo-2026-01-0{$i}.zip"), strtotime("2026-01-0{$i} 02:00:00"));
        }
        $this->assertSame(2, $svc->prune(3));
        $names = array_map('basename', Storage::disk('local')->files('backups'));
        sort($names);
        $this->assertSame(['kindo-2026-01-03.zip', 'kindo-2026-01-04.zip', 'kindo-2026-01-05.zip'], $names);
    }

    public function test_a_copy_can_be_made_to_another_folder(): void
    {
        $dir = sys_get_temp_dir() . '/kindo-offsite-' . uniqid();
        mkdir($dir);
        $this->artisan('kindo:backup', ['--copy-to' => $dir])->assertSuccessful();
        $this->assertCount(1, glob("{$dir}/kindo-*.zip"));
        $this->artisan('kindo:backup', ['--copy-to' => '/no/such/folder'])->expectsOutputToContain('Could not copy')->assertFailed();
    }

    // ---------- getting everything back ----------

    public function test_a_full_round_trip_brings_back_every_row_and_file(): void
    {
        [$u, $c, $l, $t, $f] = $this->family();
        $svc = app(BackupService::class);
        $r = $svc->create('pw');
        $before = ['users' => User::count(), 'children' => Child::count(), 'tasks' => DB::table('lesson_tasks')->count()];

        $this->wipe();
        $this->assertSame(0, User::count());
        $this->assertSame([], Storage::disk('local')->allFiles('lessons'));

        $out = $svc->restore($r['file'], 'pw');

        $this->assertSame($before, ['users' => User::count(), 'children' => Child::count(), 'tasks' => DB::table('lesson_tasks')->count()]);
        $this->assertGreaterThanOrEqual(6, $out['rows']);
        $this->assertSame(4, $out['files']);
        $user = User::where('email', 'mama@example.com')->firstOrFail();
        $this->assertSame('Mama Ünï', $user->name);                                        // accents survive
        $this->assertSame($u->id, $user->id);                                              // ids survive, so relations still line up
        $this->assertSame('الف ب', Lesson::firstOrFail()->title);
        $this->assertSame($c->id, Lesson::firstOrFail()->child_id);
        $this->assertSame('JPEGBYTES-' . str_repeat('x', 500), Storage::disk('local')->get($t->image_path));
        $this->assertSame('VOICE', Storage::disk('local')->get($f->audio_prompt_path));
        $this->assertSame('Lion', GameItem::firstOrFail()->name_en);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());                // everyone signs in again
    }

    public function test_restoring_replaces_what_is_there_and_does_not_merge(): void
    {
        $this->family();
        $svc = app(BackupService::class);
        $r = $svc->create('pw');

        User::factory()->create(['email' => 'later@example.com']);
        Storage::disk('local')->put('lessons/999/orphan.jpg', 'x');
        $svc->restore($r['file'], 'pw');

        $this->assertNull(User::where('email', 'later@example.com')->first());
        Storage::disk('local')->assertMissing('lessons/999/orphan.jpg');
        $this->assertSame(1, User::count());
    }

    public function test_the_restore_command_asks_first_and_works_with_force(): void
    {
        $this->family();
        $r = app(BackupService::class)->create('pw');
        $this->wipe();

        $this->artisan('kindo:restore', ['file' => $r['file'], '--password' => 'pw'])->expectsConfirmation('This replaces ALL current data and pictures with the backup. Continue?', 'no')->assertFailed();
        $this->assertSame(0, User::count());

        $this->artisan('kindo:restore', ['file' => $r['file'], '--password' => 'pw', '--force' => true])->expectsOutputToContain('sign in again')->assertSuccessful();
        $this->assertSame(1, User::count());
    }

    public function test_a_damaged_backup_is_caught_and_the_current_data_is_left_alone(): void
    {
        $this->family();
        $svc = app(BackupService::class);
        $r = $svc->create('pw');

        // cut the children table out of the file
        $zip = new \ZipArchive();
        $zip->open($r['file']);
        $zip->deleteName('db/children.ndjson');
        $zip->close();

        $this->assertNotEmpty($svc->verify($r['file'], 'pw'));
        try {
            $svc->restore($r['file'], 'pw');
            $this->fail('A damaged backup must not be restored.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('damaged', $e->getMessage());
        }
        $this->assertSame(1, User::count());                                               // nothing was wiped
        $this->assertSame(1, Child::count());
    }

    public function test_a_backup_from_a_newer_version_is_refused(): void
    {
        $this->family();
        $r = app(BackupService::class)->create('pw');
        $zip = new \ZipArchive();
        $zip->open($r['file']);
        $zip->setPassword('pw');
        $m = json_decode($zip->getFromName('manifest.json'), true);
        $m['migrations'][] = '2099_01_01_000000_from_the_future';
        $zip->addFromString('manifest.json', json_encode($m));
        $zip->setEncryptionName('manifest.json', \ZipArchive::EM_AES_256);
        $zip->close();

        $this->expectExceptionMessage('newer version');
        app(BackupService::class)->restore($r['file'], 'pw');
    }

    public function test_something_that_is_not_a_backup_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'nb');
        file_put_contents($path, 'not a zip at all');
        $this->expectExceptionMessage('cannot be opened');
        app(BackupService::class)->verify($path, 'pw');
    }

    // ---------- the admin can see it is working ----------

    public function test_the_health_page_reports_the_backup(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);
        $none = $this->getJson('/api/v1/admin/health')->assertOk();
        $none->assertJsonPath('backups.fresh', false)->assertJsonPath('backups.count', 0)->assertJsonPath('backups.configured', true);

        app(BackupService::class)->create('pw');
        $this->getJson('/api/v1/admin/health')->assertJsonPath('backups.fresh', true)->assertJsonPath('backups.count', 1);

        // an old one is flagged as overdue
        $file = Storage::disk('local')->files('backups')[0];
        touch(Storage::disk('local')->path($file), now()->subDays(3)->timestamp);
        $this->getJson('/api/v1/admin/health')->assertJsonPath('backups.fresh', false);
    }
}
