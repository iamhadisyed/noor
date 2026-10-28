<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MediaStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Things found by testing the running system as an attacker or a careless user would. */
class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private int $kid;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->parent = User::factory()->create(['subscription_tier' => 'pro']);
        Sanctum::actingAs($this->parent);
        $this->kid = $this->parent->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()])->id;
    }

    public function test_answers_carry_browser_protection_headers(): void
    {
        $r = $this->getJson('/api/v1/legal/terms');

        $r->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('microphone=(self)', $r->headers->get('Permissions-Policy'));
    }

    public function test_no_website_is_allowed_to_call_the_api_from_a_browser_by_default(): void
    {
        $r = $this->call('OPTIONS', '/api/v1/me', [], [], [], ['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);

        $this->assertNull($r->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_a_tiny_file_that_claims_to_be_a_huge_picture_is_refused_before_it_is_opened(): void
    {
        $png = $this->hugePng(20000, 20000);
        $this->assertLessThan(MediaStore::MAX_IMAGE_UPLOAD_KB * 1024, strlen($png), 'the file is within the normal upload size');
        $lesson = $this->postJson("/api/v1/children/{$this->kid}/lessons", ['title' => 'L'])->json();
        $task = $this->postJson("/api/v1/lessons/{$lesson['id']}/tasks", ['type' => 'pick_picture', 'title' => 'P'])->json();

        $file = UploadedFile::fake()->createWithContent('bomb.png', $png);
        $this->post("/api/v1/tasks/{$task['id']}/media", ['kind' => 'image', 'file' => $file], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('message', 'That picture is too large. Please use a smaller one.');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_normal_large_phone_photo_is_still_accepted(): void
    {
        $this->assertLessThanOrEqual(MediaStore::MAX_PIXELS_SIDE, 4000);
        $lesson = $this->postJson("/api/v1/children/{$this->kid}/lessons", ['title' => 'L'])->json();
        $task = $this->postJson("/api/v1/lessons/{$lesson['id']}/tasks", ['type' => 'pick_picture', 'title' => 'P'])->json();

        $this->post("/api/v1/tasks/{$task['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('p.jpg', 4000, 3000)], ['Accept' => 'application/json'])->assertOk();
    }

    public function test_hidden_control_and_text_direction_characters_are_removed_from_input(): void
    {
        $r = $this->postJson('/api/v1/children', ['name' => "Aya\x00n \u{202E}evil", 'date_of_birth' => now()->subYears(4)->toDateString(), 'neurotype' => 'neurotypical'])->assertCreated();

        $this->assertSame('Ayan evil', $r->json('name'));
        $lesson = $this->postJson("/api/v1/children/{$this->kid}/lessons", ['title' => "Two\nlines\tok"])->assertCreated();
        $this->assertSame("Two\nlines\tok", $lesson->json('title'), 'newlines and tabs are kept');
        $this->assertSame('Ayan', $this->getJson("/api/v1/children/{$this->kid}?x=%00")->json('name'));
    }

    public function test_impossible_birth_dates_and_practice_numbers_are_refused(): void
    {
        $this->postJson('/api/v1/children', ['name' => 'Old', 'date_of_birth' => '1800-01-01'])->assertUnprocessable()->assertJsonValidationErrors('date_of_birth');
        $this->patchJson("/api/v1/children/{$this->kid}", ['date_of_birth' => '1900-01-01'])->assertUnprocessable();

        $base = ['language' => 'urdu', 'total_attempts' => 10, 'successful_attempts' => 5, 'duration_seconds' => 600];
        $this->postJson("/api/v1/children/{$this->kid}/sessions", array_merge($base, ['total_attempts' => 1_000_000_000, 'successful_attempts' => 1_000_000_000]))->assertUnprocessable();
        $this->postJson("/api/v1/children/{$this->kid}/sessions", array_merge($base, ['duration_seconds' => 99_999_999_999]))->assertUnprocessable();
        $this->postJson("/api/v1/children/{$this->kid}/sessions", $base)->assertCreated();
    }

    public function test_the_health_page_warns_about_settings_that_are_unsafe_on_a_live_site(): void
    {
        config(['app.debug' => true, 'app.env' => 'local', 'app.url' => 'http://example.com', 'kindo.backup.password' => null]);
        Sanctum::actingAs(User::factory()->create(['role' => 'super_admin']));

        $keys = collect($this->getJson('/api/v1/admin/health')->assertOk()->json('warnings'))->pluck('level', 'key');

        $this->assertSame('danger', $keys['debug']);
        $this->assertArrayHasKey('env', $keys->all());
        $this->assertArrayHasKey('https', $keys->all());
        $this->assertArrayHasKey('backup', $keys->all());

        config(['app.debug' => false, 'app.env' => 'production', 'app.url' => 'https://kindo.example']);
        $this->assertNotContains('debug', collect($this->getJson('/api/v1/admin/health')->json('warnings'))->pluck('key')->all());
    }

    public function test_a_backup_file_can_only_restore_into_the_media_folders(): void
    {
        foreach (['lessons/1/a.jpg', 'family/3/b.mp3', 'games/2/c.png', 'aac/4/d.jpg'] as $ok) {
            $this->assertTrue(\App\Services\BackupService::isMediaPath($ok), $ok);
        }
        foreach (['../.env', 'lessons/../../.env', '/etc/passwd', 'lessons//x', 'backups/old.zip', 'lessons/x y.jpg', 'lessons/', 'other/a.jpg', "lessons/a\0.jpg"] as $bad) {
            $this->assertFalse(\App\Services\BackupService::isMediaPath($bad), $bad);
        }
    }

    public function test_a_client_that_sends_no_accept_header_still_gets_json_answers(): void
    {
        $plain = ['HTTP_ACCEPT' => '*/*'];

        // an expired token, a validation error, a missing record: no HTML page, no redirect
        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $r = $this->call('GET', '/api/v1/me', [], [], [], $plain + ['HTTP_AUTHORIZATION' => 'Bearer 1|expired']);
        $this->assertSame(401, $r->getStatusCode());
        $this->assertStringContainsString('json', $r->headers->get('Content-Type'));

        $r = $this->call('POST', '/api/v1/auth/code', [], [], [], $plain);
        $this->assertSame(422, $r->getStatusCode());
        $this->assertStringContainsString('json', $r->headers->get('Content-Type'));

        $r = $this->call('GET', '/api/v1/nothing-here', [], [], [], $plain);
        $this->assertSame(404, $r->getStatusCode());
        $this->assertStringContainsString('json', $r->headers->get('Content-Type'));
    }

    /** A valid PNG that says it is w x h pixels but compresses to almost nothing. */
    private function hugePng(int $w, int $h): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $z = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 9]);
        $row = "\x00".str_repeat("\x00", $w);
        $data = '';
        for ($i = 0; $i < $h; $i++) {
            $data .= deflate_add($z, $row, ZLIB_NO_FLUSH);
        }
        $data .= deflate_add($z, '', ZLIB_FINISH);

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 0, 0, 0, 0)).$chunk('IDAT', $data).$chunk('IEND', '');
    }
}
