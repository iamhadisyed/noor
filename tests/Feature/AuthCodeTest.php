<?php

namespace Tests\Feature;

use App\Mail\LoginCodeMail;
use App\Models\AuditLog;
use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthCodeTest extends TestCase
{
    use RefreshDatabase;

    private function ask(string $email = 'mama@example.com'): string
    {
        Mail::fake();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/code', ['email' => $email])->assertStatus(202);
        $sent = null;
        Mail::assertSent(LoginCodeMail::class, function ($m) use (&$sent) { $sent = $m->code; return true; });

        return $sent;
    }

    /** Each real HTTP request builds a fresh auth guard; tests share one, so reset it between requests. */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('');
        config(['kindo.super_admins' => []]);
    }

    public function test_code_is_emailed_and_response_is_neutral_for_known_and_unknown_emails(): void
    {
        User::factory()->create(['email' => 'known@example.com']);
        Mail::fake();

        $a = $this->postJson('/api/v1/auth/code', ['email' => 'known@example.com'])->assertStatus(202)->json('message');
        $b = $this->postJson('/api/v1/auth/code', ['email' => 'nobody@example.com'])->assertStatus(202)->json('message');

        $this->assertSame($a, $b);
        Mail::assertSent(LoginCodeMail::class, 2);
        $this->assertDatabaseMissing('login_codes', ['code_hash' => '']);
    }

    public function test_code_is_stored_hashed_never_in_clear(): void
    {
        $code = $this->ask();

        $row = LoginCode::first();
        $this->assertNotSame($code, $row->code_hash);
        $this->assertSame(LoginCode::hash($code), $row->code_hash);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function test_verify_creates_account_marks_email_verified_and_returns_a_working_token(): void
    {
        $code = $this->ask('new@example.com');

        $res = $this->postJson('/api/v1/auth/verify', ['email' => 'new@example.com', 'code' => $code, 'device_name' => 'web', 'accept_terms' => true])->assertOk();

        $res->assertJsonPath('is_new', true)->assertJsonPath('user.role', 'parent');
        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_id' => $user->id]);

        $this->withToken($res->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('email', 'new@example.com');
    }

    public function test_a_code_works_only_once(): void
    {
        $code = $this->ask();
        $this->postJson('/api/v1/auth/verify', ['email' => 'mama@example.com', 'code' => $code, 'accept_terms' => true])->assertOk();

        $this->postJson('/api/v1/auth/verify', ['email' => 'mama@example.com', 'code' => $code, 'accept_terms' => true])->assertStatus(422);
    }

    public function test_wrong_code_is_rejected_and_five_misses_kill_the_code(): void
    {
        $code = $this->ask();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/verify', ['email' => 'mama@example.com', 'code' => $wrong])->assertStatus(422);
        }
        // even the right code no longer works
        $this->postJson('/api/v1/auth/verify', ['email' => 'mama@example.com', 'code' => $code, 'accept_terms' => true])->assertStatus(422);
    }

    public function test_expired_code_is_rejected(): void
    {
        $code = $this->ask();
        $this->travel(11)->minutes();

        $this->postJson('/api/v1/auth/verify', ['email' => 'mama@example.com', 'code' => $code, 'accept_terms' => true])->assertStatus(422);
    }

    public function test_second_code_request_inside_the_cooldown_is_refused_with_retry_after(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/auth/code', ['email' => 'mama@example.com'])->assertStatus(202);

        $this->postJson('/api/v1/auth/code', ['email' => 'mama@example.com'])->assertStatus(429)->assertJsonStructure(['retry_after']);
        Mail::assertSent(LoginCodeMail::class, 1);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/auth/code', ['email' => 'mama@example.com'])->assertStatus(202);
    }

    public function test_configured_email_becomes_super_admin_and_others_do_not(): void
    {
        config(['kindo.super_admins' => ['owner@example.com']]);

        $res = $this->postJson('/api/v1/auth/verify', ['email' => 'owner@example.com', 'code' => $this->ask('owner@example.com'), 'accept_terms' => true])->assertOk();
        $res->assertJsonPath('user.role', 'super_admin');
        $this->assertSame(
            (int) config('kindo.admin_token_ttl_hours'),
            (int) round(now()->diffInHours(\Carbon\Carbon::parse($res->json('expires_at'))))
        );
        $this->as($res->json('token'))->getJson('/api/v1/admin/ping')->assertOk();

        $parent = $this->postJson('/api/v1/auth/verify', ['email' => 'mama@example.com', 'code' => $this->ask('mama@example.com'), 'accept_terms' => true])->assertOk();
        $parent->assertJsonPath('user.role', 'parent');
        $this->as($parent->json('token'))->getJson('/api/v1/admin/ping')->assertStatus(403);
    }

    public function test_admin_role_is_removed_when_the_email_leaves_the_setting(): void
    {
        $u = User::factory()->create(['email' => 'was@example.com', 'role' => 'super_admin']);

        $res = $this->postJson('/api/v1/auth/verify', ['email' => 'was@example.com', 'code' => $this->ask('was@example.com'), 'accept_terms' => true])->assertOk();

        $res->assertJsonPath('user.role', 'parent');
        $this->assertSame('parent', $u->fresh()->role);
    }

    public function test_suspended_account_cannot_sign_in_and_loses_existing_tokens(): void
    {
        $u = User::factory()->create(['email' => 'sus@example.com']);
        $token = $u->createToken('web')->plainTextToken;
        $this->as($token)->getJson('/api/v1/me')->assertOk();

        $u->update(['status' => 'suspended']);

        $this->as($token)->getJson('/api/v1/me')->assertStatus(403);
        $this->assertSame(0, $u->tokens()->count());
        $this->postJson('/api/v1/auth/verify', ['email' => 'sus@example.com', 'code' => $this->ask('sus@example.com'), 'accept_terms' => true])->assertStatus(403);
    }

    public function test_sessions_can_be_listed_and_revoked_and_logout_ends_the_current_one(): void
    {
        $u = User::factory()->create();
        $a = $u->createToken('web')->plainTextToken;
        $u->createToken('phone');

        $list = $this->withToken($a)->getJson('/api/v1/me/sessions')->assertOk()->json();
        $this->assertCount(2, $list);
        $this->assertCount(1, array_filter($list, fn ($s) => $s['current']));

        $other = collect($list)->firstWhere('device', 'phone');
        $this->withToken($a)->deleteJson("/api/v1/me/sessions/{$other['id']}")->assertOk();
        $this->assertSame(1, $u->tokens()->count());

        $this->withToken($a)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertSame(0, $u->tokens()->count());
        $this->assertTrue(AuditLog::where('action', 'auth.logout')->exists());
    }

    public function test_user_can_update_their_name(): void
    {
        $u = User::factory()->create(['name' => 'old']);

        $this->actingAs($u, 'sanctum')->patchJson('/api/v1/me', ['name' => 'Hadi Syed'])->assertOk()->assertJsonPath('name', 'Hadi Syed');
    }

    public function test_bad_input_is_validated(): void
    {
        $this->postJson('/api/v1/auth/code', ['email' => 'not-an-email'])->assertStatus(422);
        $this->postJson('/api/v1/auth/verify', ['email' => 'a@b.co', 'code' => '12'])->assertStatus(422);
    }
}
