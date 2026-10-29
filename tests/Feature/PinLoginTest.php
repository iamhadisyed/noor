<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private function withPin(string $email = 'owner@kindo.test', string $pin = '482916', array $extra = []): User
    {
        return User::factory()->create(['email' => $email, 'pin_hash' => Hash::make($pin)] + $extra);
    }

    public function test_a_pin_signs_in_and_the_token_works(): void
    {
        $u = $this->withPin();
        $r = $this->postJson('/api/v1/auth/pin', ['email' => 'OWNER@kindo.test', 'pin' => '482916'])->assertOk();
        $this->assertNotEmpty($r->json('token'));
        $this->withToken($r->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('email', 'owner@kindo.test');
        $this->assertNotNull($u->fresh()->last_login_at);
    }

    public function test_a_wrong_pin_an_unknown_email_and_an_account_without_a_pin_all_get_the_same_answer(): void
    {
        $this->withPin();
        User::factory()->create(['email' => 'nopin@kindo.test']);
        $answers = [
            $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => '000000']),
            $this->postJson('/api/v1/auth/pin', ['email' => 'nobody@kindo.test', 'pin' => '482916']),
            $this->postJson('/api/v1/auth/pin', ['email' => 'nopin@kindo.test', 'pin' => '482916']),
        ];
        foreach ($answers as $a) {
            $a->assertStatus(422)->assertJsonPath('message', 'That email or PIN is not right.');
        }
    }

    public function test_five_wrong_tries_lock_the_account_for_fifteen_minutes_even_for_the_right_pin(): void
    {
        $this->withPin();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => '111111'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => '482916'])->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(16)->minutes();
        $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => '482916'])->assertOk();
    }

    public function test_the_pin_must_be_six_digits_and_a_blocked_account_cannot_sign_in(): void
    {
        $this->withPin(extra: ['status' => 'suspended']);
        $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => '12345'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => 'abcdef'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => '482916'])->assertForbidden();
    }

    public function test_the_pin_is_never_stored_or_sent_in_clear(): void
    {
        $u = $this->withPin();
        $this->assertStringNotContainsString('482916', (string) $u->fresh()->pin_hash);
        $this->assertArrayNotHasKey('pin_hash', $u->fresh()->toArray());
        $token = $this->postJson('/api/v1/auth/pin', ['email' => 'owner@kindo.test', 'pin' => '482916'])->json('token');
        $this->assertStringNotContainsString('pin_hash', $this->withToken($token)->getJson('/api/v1/me')->getContent());
    }

    public function test_the_admin_seeder_makes_a_super_admin_with_the_documented_default_outside_production(): void
    {
        $this->seed(AdminSeeder::class);
        $r = $this->postJson('/api/v1/auth/pin', ['email' => 'admin@kindo.local', 'pin' => '482916'])->assertOk();
        $this->assertSame('super_admin', $r->json('user.role'));
        $this->withToken($r->json('token'))->getJson('/api/v1/admin/ping')->assertOk();
    }

    public function test_in_production_there_is_no_default_admin_and_a_missing_pin_is_made_up_and_not_the_documented_one(): void
    {
        config(['kindo.admin' => ['email' => '', 'pin' => '']]);
        $this->seed(AdminSeeder::class);
        $this->assertSame(0, User::count(), 'no email, no admin');

        config(['kindo.admin' => ['email' => 'boss@kindo.test', 'pin' => '']]);
        $this->seed(AdminSeeder::class);
        $this->postJson('/api/v1/auth/pin', ['email' => 'boss@kindo.test', 'pin' => '482916'])->assertStatus(422);
        $this->assertNotNull(User::where('email', 'boss@kindo.test')->first()->pin_hash);
        // running it again leaves the PIN as it is
        $hash = User::where('email', 'boss@kindo.test')->value('pin_hash');
        $this->seed(AdminSeeder::class);
        $this->assertSame($hash, User::where('email', 'boss@kindo.test')->value('pin_hash'));
    }

    public function test_the_set_pin_command_sets_changes_and_removes_a_pin_and_signs_the_old_sessions_out(): void
    {
        $u = User::factory()->create(['email' => 'p@kindo.test']);
        $this->artisan('kindo:set-pin', ['email' => 'p@kindo.test', 'pin' => '654321'])->assertSuccessful();
        $token = $this->postJson('/api/v1/auth/pin', ['email' => 'p@kindo.test', 'pin' => '654321'])->assertOk()->json('token');
        $this->artisan('kindo:set-pin', ['email' => 'p@kindo.test', 'pin' => '111222'])->assertSuccessful();
        $this->assertSame(0, $u->tokens()->count());
        $this->artisan('kindo:set-pin', ['email' => 'p@kindo.test', 'pin' => '12'])->assertFailed();
        $this->artisan('kindo:set-pin', ['email' => 'p@kindo.test', '--remove' => true])->assertSuccessful();
        $this->postJson('/api/v1/auth/pin', ['email' => 'p@kindo.test', 'pin' => '111222'])->assertStatus(422);
        $this->assertNotEmpty($token);
    }
}
