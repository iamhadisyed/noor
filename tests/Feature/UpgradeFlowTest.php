<?php

namespace Tests\Feature;

use App\Mail\ProApprovedMail;
use App\Mail\ProUpgradeAdminMail;
use App\Mail\ProUpgradeInstructionsMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Make me Pro -> we email how to pay -> I have paid -> the owner checks and switches the account to Pro. */
class UpgradeFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $mama;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['kindo.super_admins' => ['owner@kindo.test']]);
        $this->admin = User::factory()->create(['email' => 'owner@kindo.test', 'role' => 'super_admin']);
        $this->mama = User::factory()->create(['email' => 'mama@kindo.test', 'name' => 'Mama']);
    }

    public function test_the_whole_flow_from_asking_to_pro(): void
    {
        Sanctum::actingAs($this->mama);
        $this->getJson('/api/v1/me')->assertJsonPath('plan', 'free');
        $this->postJson('/api/v1/billing/upgrade', ['phone' => '0300 1234567', 'note' => 'For my son'])->assertCreated()->assertJsonPath('request.status', 'requested');
        Mail::assertSent(ProUpgradeInstructionsMail::class, fn ($m) => $m->hasTo('mama@kindo.test'));
        Mail::assertSent(ProUpgradeAdminMail::class, fn ($m) => $m->hasTo('owner@kindo.test') && $m->kind === 'requested');

        $this->postJson('/api/v1/billing/upgrade/paid', ['payment_method' => 'JazzCash', 'payment_reference' => 'TX123456', 'payment_note' => 'from 0300'])->assertOk()->assertJsonPath('request.status', 'paid_reported');
        Mail::assertSent(ProUpgradeAdminMail::class, fn ($m) => $m->kind === 'paid' && str_contains((string) $m->detail, 'TX123456'));

        Sanctum::actingAs($this->admin);
        $list = $this->getJson('/api/v1/admin/upgrades?status=open')->assertOk();
        $this->assertSame(1, $list->json('open'));
        $this->assertSame('TX123456', $list->json('data.0.payment_reference'));
        $id = $list->json('data.0.id');
        $this->postJson("/api/v1/admin/upgrades/{$id}/approve", ['days' => 365])->assertOk()->assertJsonPath('status', 'approved');
        Mail::assertSent(ProApprovedMail::class, fn ($m) => $m->hasTo('mama@kindo.test'));

        Sanctum::actingAs($this->mama->fresh());
        $this->getJson('/api/v1/me')->assertJsonPath('plan', 'pro');
        $this->getJson('/api/v1/billing')->assertJsonPath('plan', 'pro');
        $this->getJson('/api/v1/guidance/autism')->assertOk();
        $this->assertTrue($this->mama->fresh()->subscription_expires_at->between(now()->addDays(364), now()->addDays(366)));
    }

    public function test_asking_twice_does_not_make_two_requests_or_send_two_emails(): void
    {
        Sanctum::actingAs($this->mama);
        $this->postJson('/api/v1/billing/upgrade')->assertCreated();
        $this->postJson('/api/v1/billing/upgrade')->assertOk()->assertJsonPath('request.status', 'requested');
        $this->assertSame(1, \App\Models\UpgradeRequest::count());
        Mail::assertSent(ProUpgradeInstructionsMail::class, 1);
    }

    public function test_paying_needs_a_request_first_and_details_are_required(): void
    {
        Sanctum::actingAs($this->mama);
        $this->postJson('/api/v1/billing/upgrade/paid', ['payment_method' => 'Bank', 'payment_reference' => 'x'])->assertUnprocessable();
        $this->postJson('/api/v1/billing/upgrade')->assertCreated();
        $this->postJson('/api/v1/billing/upgrade/paid', ['payment_method' => 'Bank'])->assertUnprocessable();
    }

    public function test_the_owner_can_say_no_with_a_reason_the_parent_sees_and_a_handled_request_cannot_be_handled_twice(): void
    {
        Sanctum::actingAs($this->mama);
        $this->postJson('/api/v1/billing/upgrade')->assertCreated();
        Sanctum::actingAs($this->admin);
        $id = $this->getJson('/api/v1/admin/upgrades')->json('data.0.id');
        $this->postJson("/api/v1/admin/upgrades/{$id}/reject", [])->assertUnprocessable();
        $this->postJson("/api/v1/admin/upgrades/{$id}/reject", ['admin_note' => 'We could not find the payment'])->assertOk();
        $this->postJson("/api/v1/admin/upgrades/{$id}/approve", ['days' => 30])->assertUnprocessable();
        Sanctum::actingAs($this->mama);
        $this->getJson('/api/v1/billing/upgrade')->assertJsonPath('request.status', 'rejected')->assertJsonPath('request.admin_note', 'We could not find the payment');
        $this->getJson('/api/v1/me')->assertJsonPath('plan', 'free');
        // she can ask again
        $this->postJson('/api/v1/billing/upgrade')->assertCreated();
    }

    public function test_parents_cannot_use_the_owners_side_and_the_owners_notes_stay_private(): void
    {
        Sanctum::actingAs($this->mama);
        $this->getJson('/api/v1/admin/upgrades')->assertForbidden();
        $this->postJson('/api/v1/admin/upgrades/1/approve', ['days' => 9999])->assertForbidden();
        $this->postJson('/api/v1/billing/upgrade')->assertCreated();
        $this->assertArrayNotHasKey('note', $this->getJson('/api/v1/billing/upgrade')->json('request'));
        $this->assertArrayNotHasKey('user', $this->getJson('/api/v1/billing/upgrade')->json('request'));
    }

    public function test_a_demo_account_cannot_send_these_emails(): void
    {
        $demo = User::factory()->create(['is_demo' => true]);
        Sanctum::actingAs($demo);
        $this->postJson('/api/v1/billing/upgrade')->assertForbidden();
        $this->postJson('/api/v1/household/invite', ['email' => 'x@y.com'])->assertForbidden();
        $this->deleteJson('/api/v1/me', ['code' => '123456'])->assertForbidden();
        Mail::assertNothingSent();
    }
}
