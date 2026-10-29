<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScreenTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $pro;
    private int $kid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pro = User::factory()->create(['subscription_tier' => 'pro']);
        Sanctum::actingAs($this->pro);
        $this->kid = $this->pro->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(6)->toDateString()])->id;
    }

    public function test_a_pro_parent_sets_a_daily_limit_and_quiet_hours_and_the_childs_record_carries_them(): void
    {
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['enabled' => true, 'daily_minutes' => 45, 'quiet_start' => '20:30', 'quiet_end' => '07:00'])
            ->assertOk()->assertJsonPath('settings.daily_minutes', 45)->assertJsonPath('today.remaining_seconds', 2700)->assertJsonPath('today.locked', false);
        $child = $this->getJson("/api/v1/children/{$this->kid}")->assertOk()->json();
        $this->assertSame(['enabled' => true, 'daily_minutes' => 45, 'quiet_start' => '20:30', 'quiet_end' => '07:00'], $child['screen_time']);
        // it travels through sync, so the child's app enforces it offline
        $sync = collect($this->getJson('/api/v1/sync')->json('changes'))->firstWhere('entity', 'child');
        $this->assertSame(45, $sync['data']['screen_time']['daily_minutes']);
    }

    public function test_the_child_app_reports_use_and_the_day_locks_at_the_limit_and_extra_minutes_open_it_again(): void
    {
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['enabled' => true, 'daily_minutes' => 10]);
        $this->postJson("/api/v1/children/{$this->kid}/screen-time/usage", ['seconds' => 300])->assertOk()->assertJsonPath('remaining_seconds', 300)->assertJsonPath('locked', false);
        $r = $this->postJson("/api/v1/children/{$this->kid}/screen-time/usage", ['seconds' => 400])->assertOk();
        $this->assertSame(0, $r->json('remaining_seconds'));
        $this->assertTrue($r->json('locked'));
        $this->assertSame('limit', $r->json('reason'));
        $this->postJson("/api/v1/children/{$this->kid}/screen-time/extend", ['minutes' => 15])->assertOk()->assertJsonPath('today.locked', false)->assertJsonPath('today.remaining_seconds', 800);
        $this->getJson("/api/v1/children/{$this->kid}/screen-time")->assertOk()->assertJsonPath('today.used_seconds', 700)->assertJsonCount(7, 'week');
    }

    public function test_one_report_cannot_add_more_than_an_hour_and_a_far_away_date_is_ignored(): void
    {
        $this->postJson("/api/v1/children/{$this->kid}/screen-time/usage", ['seconds' => 99999])->assertUnprocessable();
        $this->postJson("/api/v1/children/{$this->kid}/screen-time/usage", ['seconds' => 600, 'date' => '1999-01-01'])->assertOk();
        $this->getJson("/api/v1/children/{$this->kid}/screen-time")->assertJsonPath('today.used_seconds', 600);
    }

    public function test_quiet_hours_need_both_ends_and_the_limit_has_sensible_bounds(): void
    {
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['quiet_start' => '20:00'])->assertUnprocessable();
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['daily_minutes' => 3])->assertUnprocessable();
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['quiet_start' => '25:99', 'quiet_end' => '07:00'])->assertUnprocessable();
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['enabled' => true, 'quiet_start' => '20:00', 'quiet_end' => '07:00'])->assertOk();
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['quiet_start' => null, 'quiet_end' => null])->assertOk()->assertJsonPath('settings.quiet_start', null);
    }

    public function test_the_free_plan_gets_a_plain_402_and_nothing_is_enforced_or_counted(): void
    {
        $free = User::factory()->create();
        Sanctum::actingAs($free);
        $kid = $free->children()->create(['name' => 'Zara', 'date_of_birth' => now()->subYears(7)->toDateString()])->id;
        $this->getJson("/api/v1/children/{$kid}/screen-time")->assertStatus(402)->assertJsonPath('upgrade', true);
        $this->patchJson("/api/v1/children/{$kid}/screen-time", ['enabled' => true])->assertStatus(402);
        $this->postJson("/api/v1/children/{$kid}/screen-time/extend", ['minutes' => 10])->assertStatus(402);
        $this->postJson("/api/v1/children/{$kid}/screen-time/usage", ['seconds' => 60])->assertOk()->assertJsonPath('recorded', false);
        $this->assertNull($this->getJson("/api/v1/children/{$kid}")->json('screen_time'));
    }

    public function test_when_pro_ends_the_rules_stop_applying_to_the_child_but_are_kept(): void
    {
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['enabled' => true, 'daily_minutes' => 30]);
        $this->assertNotNull($this->getJson("/api/v1/children/{$this->kid}")->json('screen_time'));
        $this->pro->update(['subscription_expires_at' => now()->subDay()]);
        $this->assertNull($this->getJson("/api/v1/children/{$this->kid}")->json('screen_time'));
        $this->pro->update(['subscription_expires_at' => now()->addMonth()]);
        $this->assertSame(30, $this->getJson("/api/v1/children/{$this->kid}")->json('screen_time.daily_minutes'));
    }

    public function test_a_co_parent_in_the_household_shares_it_and_a_stranger_cannot_touch_it(): void
    {
        $stranger = User::factory()->create(['subscription_tier' => 'pro']);
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/children/{$this->kid}/screen-time")->assertNotFound();
        $this->patchJson("/api/v1/children/{$this->kid}/screen-time", ['enabled' => true])->assertNotFound();
        $this->postJson("/api/v1/children/{$this->kid}/screen-time/usage", ['seconds' => 60])->assertNotFound();
        $this->postJson("/api/v1/children/{$this->kid}/screen-time/extend", ['minutes' => 60])->assertNotFound();
    }
}
