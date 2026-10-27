<?php

namespace Tests\Feature;

use App\Mail\LoginCodeMail;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\ContentFlag;
use App\Models\FamilyMember;
use App\Models\Lesson;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->parent = User::factory()->create(['subscription_tier' => 'pro', 'email' => 'mama@example.com', 'terms_version' => '1.0', 'terms_accepted_at' => now()]);
        $this->as($this->parent);
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function wav(): UploadedFile
    {
        $data = str_repeat("\x00\x00", 2000);
        $hdr = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16) . 'data' . pack('V', strlen($data));
        $path = tempnam(sys_get_temp_dir(), 'snd') . '.wav';
        file_put_contents($path, $hdr . $data);

        return new UploadedFile($path, 'hello.wav', 'audio/wav', null, true);
    }

    /** A family with a lesson that has a picture and a sound, a family card, and an alert. */
    private function fill(User $p): array
    {
        $this->as($p);
        $c = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => '2020-01-01'])->json();
        $l = $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'Week 1'])->json();
        $t = $this->postJson("/api/v1/lessons/{$l['id']}/tasks", ['type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا'])->json();
        $this->post("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('a.jpg', 300, 300)], ['Accept' => 'application/json'])->assertOk();
        $this->post("/api/v1/tasks/{$t['id']}/media", ['kind' => 'audio', 'file' => $this->wav()], ['Accept' => 'application/json'])->assertOk();
        $f = $this->postJson("/api/v1/children/{$c['id']}/family", ['relation_title' => 'Mama', 'display_name' => 'Ammi', 'photo' => UploadedFile::fake()->image('p.jpg', 300, 300), 'audio' => $this->wav()])->assertCreated()->json();
        ChildNeedsLog::create(['child_id' => $c['id'], 'need_type' => 'water']);

        return [$c, $l, $t, $f];
    }

    private function openZip($response): array
    {
        $file = $response->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'z');
        copy($file, $copy);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($copy));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) { $names[] = $zip->getNameIndex($i); }
        $json = json_decode($zip->getFromName('data.json'), true);

        return [$names, $json, $zip];
    }

    // ---------- export ----------

    public function test_the_export_holds_the_data_and_the_pictures_and_voices(): void
    {
        [$c, $l, $t, $f] = $this->fill($this->parent);

        $res = $this->get('/api/v1/me/export', ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/zip');
        [$names, $json, $zip] = $this->openZip($res);

        $this->assertContains('data.json', $names);
        $this->assertSame('mama@example.com', $json['account']['email']);
        $this->assertSame('1.0', $json['consent']['terms_version']);
        $this->assertSame('Ayan', $json['children'][0]['name']);
        $this->assertSame('Week 1', $json['lessons'][0]['title']);
        $task = $json['lessons'][0]['tasks'][0];
        $this->assertSame("media/lessons/{$l['id']}/task-{$t['id']}-picture.jpg", $task['picture_file']);
        $this->assertContains($task['picture_file'], $names);
        $this->assertContains($task['sound_file'], $names);
        $this->assertContains($json['family_cards'][0]['photo_file'], $names);
        $this->assertContains($json['family_cards'][0]['voice_file'], $names);
        $this->assertSame('water', $json['alerts'][0]['need_type']);
        $this->assertSame('JPEG', strtoupper(substr(getimagesizefromstring($zip->getFromName($task['picture_file']))['mime'], 6)));
        $this->assertStringNotContainsString('image_path', json_encode($json));                       // no server paths
        $this->assertTrue(AuditLog::where('action', 'account.exported')->where('actor_id', $this->parent->id)->exists());
    }

    public function test_the_export_never_includes_another_familys_data(): void
    {
        $this->fill($this->parent);
        $other = User::factory()->create(['subscription_tier' => 'pro', 'email' => 'other@example.com']);
        $this->as($other);
        $this->postJson('/api/v1/children', ['name' => 'Secretchild', 'date_of_birth' => '2020-01-01'])->assertCreated();

        $res = $this->as($this->parent)->get('/api/v1/me/export', ['Accept' => 'application/json'])->assertOk();
        [, $json] = $this->openZip($res);
        $this->assertStringNotContainsString('Secretchild', json_encode($json));
        $this->assertStringNotContainsString('other@example.com', json_encode($json));
    }

    public function test_an_empty_account_exports_and_a_stranger_cannot(): void
    {
        $res = $this->get('/api/v1/me/export', ['Accept' => 'application/json'])->assertOk();
        [, $json] = $this->openZip($res);
        $this->assertSame([], $json['children']);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/me/export')->assertStatus(401);
    }

    // ---------- delete ----------

    private function deleteCode(): string
    {
        Mail::fake();
        $this->postJson('/api/v1/me/delete-code')->assertStatus(202);
        $code = null;
        Mail::assertSent(LoginCodeMail::class, function ($m) use (&$code) { $code = $m; return true; });
        $this->assertSame('delete', $code->purpose);

        return $code->code;
    }

    public function test_deleting_needs_the_emailed_code_then_removes_everything_including_files(): void
    {
        [$c, $l, $t, $f] = $this->fill($this->parent);
        $this->assertNotSame([], Storage::disk('local')->allFiles());
        $this->parent->createToken('phone');
        \App\Models\DeviceToken::create(['user_id' => $this->parent->id, 'token' => 'tok', 'device_type' => 'android']);
        ContentFlag::create(['user_id' => $this->parent->id, 'lesson_id' => $l['id'], 'reason' => 'manual']);

        $this->deleteJson('/api/v1/me', ['code' => '123456'])->assertStatus(422);       // no code asked for yet
        $code = $this->deleteCode();
        $this->deleteJson('/api/v1/me', ['code' => $code === '000000' ? '111111' : '000000'])->assertStatus(422);
        $this->assertNotNull(User::find($this->parent->id));                             // a wrong code deletes nothing

        $this->deleteJson('/api/v1/me', ['code' => $code])->assertOk();

        $this->assertNull(User::find($this->parent->id));
        $this->assertSame(0, Child::count());
        $this->assertSame(0, Lesson::count());
        $this->assertSame(0, FamilyMember::count());
        $this->assertSame(0, ChildNeedsLog::count());
        $this->assertSame(0, ContentFlag::count());
        $this->assertSame(0, \App\Models\DeviceToken::count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('personal_access_tokens')->count());
        $this->assertSame(0, LoginCode::where('email', 'mama@example.com')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());                       // pictures and voices are gone from disk
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_the_audit_log_remembers_the_deletion_without_the_email(): void
    {
        $this->fill($this->parent);
        $code = $this->deleteCode();
        $this->deleteJson('/api/v1/me', ['code' => $code])->assertOk();

        $row = AuditLog::where('action', 'account.deleted')->firstOrFail();
        $this->assertNull($row->actor_id);
        $this->assertStringNotContainsString('mama@example.com', json_encode($row->meta));
        $this->assertStringNotContainsString('Ayan', json_encode($row->meta));
    }

    public function test_a_code_is_single_use_and_five_wrong_tries_kill_it(): void
    {
        $this->fill($this->parent);
        $code = $this->deleteCode();
        $wrong = $code === '999999' ? '888888' : '999999';
        for ($i = 0; $i < 5; $i++) { $this->deleteJson('/api/v1/me', ['code' => $wrong])->assertStatus(422); }
        $this->deleteJson('/api/v1/me', ['code' => $code])->assertStatus(422);           // now dead
        $this->assertNotNull(User::find($this->parent->id));
    }

    public function test_a_delete_code_does_not_sign_anyone_in_and_a_sign_in_code_does_not_delete(): void
    {
        $this->fill($this->parent);
        $code = $this->deleteCode();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->postJson('/api/v1/auth/verify', ['email' => 'mama@example.com', 'code' => $code, 'accept_terms' => true])->assertStatus(422);

        // and the other way round
        Mail::fake();
        $this->postJson('/api/v1/auth/code', ['email' => 'mama@example.com'])->assertStatus(202);
        $login = null;
        Mail::assertSent(LoginCodeMail::class, function ($m) use (&$login) { $login = $m; return $m->purpose === 'login'; });
        $this->as($this->parent)->deleteJson('/api/v1/me', ['code' => $login->code === $code ? '000001' : $login->code])->assertStatus(422);
        $this->assertNotNull(User::find($this->parent->id));
    }

    public function test_the_administrator_account_cannot_be_deleted_here(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->as($admin)->postJson('/api/v1/me/delete-code')->assertStatus(422);
        $this->as($admin)->deleteJson('/api/v1/me', ['code' => '123456'])->assertStatus(422);
        $this->assertNotNull(User::find($admin->id));
    }

    public function test_only_the_signed_in_parent_is_deleted_and_other_families_are_untouched(): void
    {
        $other = User::factory()->create(['subscription_tier' => 'pro', 'email' => 'other@example.com']);
        $this->fill($other);
        $filesBefore = count(Storage::disk('local')->allFiles());
        $this->fill($this->parent);

        $code = $this->deleteCode();
        $this->deleteJson('/api/v1/me', ['code' => $code])->assertOk();

        $this->assertNotNull(User::find($other->id));
        $this->assertSame(1, Child::count());
        $this->assertSame($filesBefore, count(Storage::disk('local')->allFiles()));
    }

    public function test_deleting_one_child_still_removes_its_files(): void
    {
        [$c] = $this->fill($this->parent);
        $this->deleteJson("/api/v1/children/{$c['id']}")->assertOk();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertTrue(AuditLog::where('action', 'child.deleted')->exists());
    }
}
