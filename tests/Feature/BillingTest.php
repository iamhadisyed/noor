<?php

namespace Tests\Feature;

use App\Mail\ProExpiringMail;
use App\Models\AuditLog;
use App\Models\HouseholdMember;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function voucher(int $days = 30, array $extra = []): Voucher
    {
        $v = app(BillingService::class)->makeVouchers(1, $days, 'test', null, null)[0];
        if ($extra) {
            $v->update($extra);
        }

        return $v->fresh();
    }

    // ---------- redeeming ----------

    public function test_a_free_parent_redeems_a_code_and_becomes_pro_for_those_days(): void
    {
        $u = User::factory()->create();
        $v = $this->voucher(30);

        $res = $this->as($u)->postJson('/api/v1/billing/redeem', ['code' => $v->display()])->assertOk();
        $res->assertJsonPath('plan', 'pro')->assertJsonPath('history.0.source', 'voucher')->assertJsonPath('history.0.days', 30);
        $this->assertTrue($u->fresh()->isPro());
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, $u->fresh()->subscription_expires_at->timestamp, 5);
        $this->assertSame('used', $v->fresh()->status());
        $this->assertSame($u->id, $v->fresh()->used_by);
        $this->assertTrue(AuditLog::where('action', 'billing.redeemed')->where('actor_id', $u->id)->exists());
    }

    public function test_codes_work_however_they_are_typed(): void
    {
        $u = User::factory()->create();
        $v = $this->voucher(7);
        $typed = ' ' . strtolower(substr($v->code, 0, 4)) . ' - ' . strtolower(substr($v->code, 4)) . ' ';
        $this->as($u)->postJson('/api/v1/billing/redeem', ['code' => $typed])->assertOk();
    }

    public function test_redeeming_while_pro_adds_to_the_end_date_and_wastes_nothing(): void
    {
        $u = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->addDays(10)]);
        $this->as($u)->postJson('/api/v1/billing/redeem', ['code' => $this->voucher(30)->code])->assertOk();
        $this->assertEqualsWithDelta(now()->addDays(40)->timestamp, $u->fresh()->subscription_expires_at->timestamp, 5);

        $h = $this->getJson('/api/v1/billing')->json('history');
        $this->assertCount(1, $h);
        $this->assertEqualsWithDelta(now()->addDays(10)->timestamp, strtotime($h[0]['starts_at']), 5);   // the new period starts when the old one ends
    }

    public function test_an_ended_pro_starts_again_from_today(): void
    {
        $u = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->subDays(5)]);
        $this->assertFalse($u->isPro());
        $this->as($u)->postJson('/api/v1/billing/redeem', ['code' => $this->voucher(10)->code])->assertOk();
        $this->assertEqualsWithDelta(now()->addDays(10)->timestamp, $u->fresh()->subscription_expires_at->timestamp, 5);
    }

    public function test_a_code_works_once_only(): void
    {
        $v = $this->voucher();
        $this->as(User::factory()->create())->postJson('/api/v1/billing/redeem', ['code' => $v->code])->assertOk();
        $second = User::factory()->create();
        $this->as($second)->postJson('/api/v1/billing/redeem', ['code' => $v->code])->assertStatus(422);
        $this->assertFalse($second->fresh()->isPro());
        $this->assertSame(1, Subscription::count());
    }

    public function test_bad_expired_revoked_and_short_codes_all_get_the_same_neutral_answer(): void
    {
        $u = User::factory()->create();
        $expired = $this->voucher(30, ['expires_at' => now()->subDay()]);
        $revoked = $this->voucher(30, ['revoked_at' => now()]);
        $used = $this->voucher(30);
        $this->as(User::factory()->create())->postJson('/api/v1/billing/redeem', ['code' => $used->code])->assertOk();

        $messages = [];
        foreach (['ZZZZZZZZ', $expired->code, $revoked->code, $used->code, 'ABC', '!!!!'] as $code) {
            $r = $this->as($u)->postJson('/api/v1/billing/redeem', ['code' => $code])->assertStatus(422);
            $messages[] = $r->json('message');
        }
        $this->assertCount(1, array_unique($messages), 'every refusal reads the same, so codes cannot be probed');
        $this->assertFalse($u->fresh()->isPro());
        $this->postJson('/api/v1/billing/redeem', [])->assertStatus(422);
    }

    public function test_guessing_is_rate_limited(): void
    {
        $u = User::factory()->create();
        $this->as($u);
        foreach (range(1, 10) as $i) { $this->postJson('/api/v1/billing/redeem', ['code' => 'ZZZZZZZ' . $i])->assertStatus(422); }
        $this->postJson('/api/v1/billing/redeem', ['code' => $this->voucher()->code])->assertStatus(429);
    }

    public function test_a_lifetime_pro_is_told_there_is_nothing_to_add_and_keeps_the_code(): void
    {
        $u = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => null]);
        $v = $this->voucher();
        $this->as($u)->postJson('/api/v1/billing/redeem', ['code' => $v->code])->assertStatus(422)->assertJsonPath('message', 'Your Pro plan has no end date, so there is nothing to add.');
        $this->assertSame('unused', $v->fresh()->status());
        $this->assertNull($u->fresh()->subscription_expires_at);
    }

    public function test_pro_limits_apply_straight_after_redeeming(): void
    {
        $u = User::factory()->create();
        $this->as($u);
        $child = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => '2020-01-01'])->json();
        foreach (range(1, 3) as $i) { $this->postJson("/api/v1/children/{$child['id']}/lessons", ['title' => "L{$i}"])->assertCreated(); }
        $this->postJson("/api/v1/children/{$child['id']}/lessons", ['title' => 'L4'])->assertStatus(402);

        $this->postJson('/api/v1/billing/redeem', ['code' => $this->voucher()->code])->assertOk();
        $this->postJson("/api/v1/children/{$child['id']}/lessons", ['title' => 'L4'])->assertCreated();
    }

    // ---------- seeing your plan ----------

    public function test_the_plan_page_shows_limits_history_and_days_left(): void
    {
        $u = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->addDays(12)]);
        $r = $this->as($u)->getJson('/api/v1/billing')->assertOk();
        $r->assertJsonPath('plan', 'pro')->assertJsonPath('days_left', 12)->assertJsonPath('store_purchase', false)->assertJsonPath('limits.free.lessons_per_child', 3)->assertJsonPath('via_household', false);

        $free = $this->as(User::factory()->create())->getJson('/api/v1/billing')->assertOk();
        $free->assertJsonPath('plan', 'free')->assertJsonPath('expires_at', null)->assertJsonPath('history', []);
    }

    public function test_a_co_parent_sees_the_shared_plan_but_cannot_redeem_for_the_owner(): void
    {
        $owner = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->addDays(20)]);
        $baba = User::factory()->create(['email' => 'baba@example.com']);
        HouseholdMember::create(['owner_id' => $owner->id, 'email' => $baba->email, 'user_id' => $baba->id, 'status' => 'active']);

        $r = $this->as($baba)->getJson('/api/v1/billing')->assertOk();
        $r->assertJsonPath('plan', 'pro')->assertJsonPath('via_household', true)->assertJsonPath('expires_at', null);
    }

    // ---------- the owner's side ----------

    public function test_only_the_super_admin_manages_vouchers_and_grants(): void
    {
        $p = User::factory()->create();
        $this->as($p)->getJson('/api/v1/admin/vouchers')->assertStatus(403);
        $this->postJson('/api/v1/admin/vouchers', ['days' => 30, 'count' => 1])->assertStatus(403);
        $this->postJson("/api/v1/admin/users/{$p->id}/grant", ['days' => 30])->assertStatus(403);
    }

    public function test_the_admin_makes_a_batch_lists_it_and_sees_who_used_one(): void
    {
        $admin = $this->admin();
        $made = $this->as($admin)->postJson('/api/v1/admin/vouchers', ['days' => 365, 'count' => 5, 'note' => 'Spring school batch'])->assertCreated()->json('vouchers');
        $this->assertCount(5, $made);
        $this->assertCount(5, array_unique(array_column($made, 'code')));
        $this->assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{4}-[A-HJKMNP-Z2-9]{4}$/', $made[0]['code']);

        $mama = User::factory()->create(['email' => 'mama@example.com']);
        $this->as($mama)->postJson('/api/v1/billing/redeem', ['code' => $made[0]['code']])->assertOk();

        $all = $this->as($admin)->getJson('/api/v1/admin/vouchers')->assertOk();
        $this->assertSame(['unused' => 4, 'used' => 1], $all->json('counts'));
        $this->assertSame('mama@example.com', collect($all->json('data'))->firstWhere('id', $made[0]['id'])['used_by']);
        $this->assertCount(4, $this->getJson('/api/v1/admin/vouchers?status=unused')->json('data'));
        $this->assertTrue(AuditLog::where('action', 'admin.vouchers_created')->exists());
    }

    public function test_batches_are_validated(): void
    {
        $this->as($this->admin());
        foreach ([['days' => 0, 'count' => 1], ['days' => 30, 'count' => 0], ['days' => 30, 'count' => 101], ['days' => 99999, 'count' => 1], ['days' => 30, 'count' => 1, 'expires_at' => '2020-01-01']] as $bad) {
            $this->postJson('/api/v1/admin/vouchers', $bad)->assertStatus(422);
        }
    }

    public function test_an_unused_code_can_be_cancelled_and_a_used_one_cannot(): void
    {
        $admin = $this->admin();
        $a = $this->voucher();
        $b = $this->voucher();
        $this->as(User::factory()->create())->postJson('/api/v1/billing/redeem', ['code' => $b->code])->assertOk();

        $this->as($admin)->deleteJson("/api/v1/admin/vouchers/{$a->id}")->assertOk()->assertJsonPath('status', 'revoked');
        $this->deleteJson("/api/v1/admin/vouchers/{$b->id}")->assertStatus(422);
        $this->as(User::factory()->create())->postJson('/api/v1/billing/redeem', ['code' => $a->code])->assertStatus(422);
    }

    public function test_the_admin_can_grant_pro_time_by_hand_and_it_stacks(): void
    {
        $mama = User::factory()->create();
        $this->as($this->admin())->postJson("/api/v1/admin/users/{$mama->id}/grant", ['days' => 14, 'note' => 'Thank you for the feedback'])->assertOk()->assertJsonPath('plan', 'pro');
        $this->postJson("/api/v1/admin/users/{$mama->id}/grant", ['days' => 14])->assertOk();
        $this->assertEqualsWithDelta(now()->addDays(28)->timestamp, $mama->fresh()->subscription_expires_at->timestamp, 5);
        $this->assertSame(['admin', 'admin'], Subscription::pluck('source')->all());
        $this->postJson('/api/v1/admin/users/9999/grant', ['days' => 5])->assertStatus(404);
        $this->postJson("/api/v1/admin/users/{$mama->id}/grant", ['days' => 0])->assertStatus(422);
    }

    // ---------- expiry ----------

    public function test_a_warning_goes_out_three_days_ahead_once(): void
    {
        Mail::fake();
        $soon = User::factory()->create(['name' => 'Mama', 'email' => 'soon@example.com', 'subscription_tier' => 'pro', 'subscription_expires_at' => now()->addDays(2)]);
        User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->addDays(20)]);

        $this->artisan('kindo:billing-daily')->expectsOutputToContain('Warned 1')->assertSuccessful();
        Mail::assertSent(ProExpiringMail::class, fn ($m) => $m->hasTo('soon@example.com'));
        Mail::assertSent(ProExpiringMail::class, 1);

        $this->artisan('kindo:billing-daily')->expectsOutputToContain('Warned 0')->assertSuccessful();   // not nagged again
        Mail::assertSent(ProExpiringMail::class, 1);

        // after renewing, the next end date gets its own warning when it comes near
        $soon->update(['subscription_expires_at' => now()->addDays(2)->addMonth()]);
        $this->artisan('kindo:billing-daily')->expectsOutputToContain('Warned 0')->assertSuccessful();   // far away now
        $soon->update(['subscription_expires_at' => now()->addDays(2)->addDay()->subHours(1)]);          // a different date that is close again
        $this->artisan('kindo:billing-daily')->assertSuccessful();
        $this->assertSame(now()->addDays(2)->addDay()->subHours(1)->toDateString(), $soon->fresh()->subscription_expires_at->toDateString());
    }

    public function test_ended_plans_move_back_to_free(): void
    {
        $gone = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->subDay()]);
        $kept = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => now()->addDays(30)]);
        $life = User::factory()->create(['subscription_tier' => 'pro', 'subscription_expires_at' => null]);

        $this->artisan('kindo:billing-daily')->expectsOutputToContain('ended 1');
        $this->assertSame('free', $gone->fresh()->subscription_tier);
        $this->assertSame('pro', $kept->fresh()->subscription_tier);
        $this->assertSame('pro', $life->fresh()->subscription_tier);
    }
}
