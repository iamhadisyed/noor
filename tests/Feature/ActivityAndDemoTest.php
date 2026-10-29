<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityAndDemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function demo(string $which = 'parent'): User
    {
        $this->seed(DemoSeeder::class);

        return User::where('email', config("kindo.demo.{$which}.email"))->firstOrFail();
    }

    public function test_the_demo_accounts_sign_in_with_their_pin_have_full_pro_and_a_ready_family(): void
    {
        $this->demo();
        foreach (['parent' => ['Ayan', 'Zara'], 'child' => ['Mina', 'Hamza']] as $which => $names) {
            $r = $this->postJson('/api/v1/auth/pin', ['email' => config("kindo.demo.{$which}.email"), 'pin' => config("kindo.demo.{$which}.pin")])->assertOk();
            $this->assertSame('pro', $r->json('user.plan'));
            $this->assertTrue($r->json('user.is_demo'));
            $this->assertFalse($r->json('user.terms_required'));
            $this->assertNull($r->json('user.ads'), 'no ads in the demo');
            $this->app['auth']->forgetGuards();   // a new request, a new sign-in
            $kids = $this->withToken($r->json('token'))->getJson('/api/v1/children')->assertOk()->json();
            $this->assertEqualsCanonicalizing($names, array_column($kids, 'name'));
        }
    }

    public function test_the_demo_family_is_rich_enough_to_show_every_screen(): void
    {
        $u = $this->demo();
        Sanctum::actingAs($u);
        $ayan = $u->children()->where('name', 'Ayan')->first();
        $this->assertSame('special', $ayan->track);
        $lessons = $this->getJson("/api/v1/children/{$ayan->id}/lessons")->json();
        $this->assertGreaterThanOrEqual(3, count($lessons));
        foreach ($lessons as $l) {
            $this->assertSame('published', $l['status']);
        }
        $this->assertGreaterThanOrEqual(2, count($this->getJson("/api/v1/children/{$ayan->id}/family")->json()));
        $this->assertNotEmpty($this->getJson('/api/v1/aac-cards')->json());
        $this->assertGreaterThan(0, $this->getJson("/api/v1/children/{$ayan->id}/rewards")->json('balance_minutes'));
        $this->assertNotEmpty($this->getJson("/api/v1/children/{$ayan->id}/letters")->json('letters'));
        $st = $this->getJson("/api/v1/children/{$ayan->id}/screen-time")->assertOk();
        $this->assertTrue($st->json('settings.enabled'));
        $this->assertCount(7, $st->json('week'));
        $this->assertGreaterThan(0, array_sum(array_column($st->json('week'), 'seconds')));
    }

    public function test_the_demo_is_put_back_every_night_and_a_reset_removes_what_visitors_added(): void
    {
        $u = $this->demo();
        $u->children()->create(['name' => 'Visitor', 'date_of_birth' => now()->subYears(4)->toDateString()]);
        $this->assertSame(3, $u->children()->count());
        $this->artisan('kindo:demo-reset')->assertSuccessful();
        $u = User::where('email', config('kindo.demo.parent.email'))->first();
        $this->assertSame(['Ayan', 'Zara'], $u->children()->orderBy('id')->pluck('name')->all());
        $this->assertSame(1, User::where('email', config('kindo.demo.parent.email'))->count());
    }

    public function test_demo_accounts_are_off_when_switched_off(): void
    {
        config(['kindo.demo.enabled' => false]);
        $this->seed(DemoSeeder::class);
        $this->assertSame(0, User::where('is_demo', true)->count());
    }

    public function test_the_activity_list_shows_everything_the_child_did_and_the_free_plan_sees_today_only(): void
    {
        $u = $this->demo();
        $ayan = $u->children()->where('name', 'Ayan')->first();
        $ayan->needsLogs()->create(['need_type' => 'water']);
        $ayan->practiceSessions()->create(['language' => 'english', 'total_attempts' => 5, 'successful_attempts' => 4, 'duration_seconds' => 300]);
        Sanctum::actingAs($u);
        $week = $this->getJson("/api/v1/children/{$ayan->id}/activity?days=7")->assertOk();
        $kinds = collect($week->json('items'))->pluck('kind')->unique()->sort()->values()->all();
        $this->assertSame(['calm', 'game', 'need', 'practice', 'reward'], $kinds);
        $this->assertFalse($week->json('limited'));
        $this->assertCount(7, $week->json('screen_time'));
        $this->assertGreaterThan(0, $week->json('summary.practice_minutes'));
        $this->assertContains('Asked for: Water', collect($week->json('items'))->pluck('title')->all());
        $times = collect($week->json('items'))->pluck('at')->all();
        $sorted = $times;
        rsort($sorted);
        $this->assertSame($sorted, $times, 'newest first');

        // the same child on the free plan: today only, no screen time, and a hint about Pro
        $u->update(['subscription_tier' => 'free', 'is_demo' => false]);
        $today = $this->getJson("/api/v1/children/{$ayan->id}/activity?days=7")->assertOk();
        $this->assertTrue($today->json('limited'));
        $this->assertSame(1, $today->json('days'));
        $this->assertArrayNotHasKey('screen_time', $today->json());
        $this->assertNotEmpty($today->json('upgrade_hint'));
        foreach ($today->json('items') as $i) {
            $this->assertTrue(now()->parse($i['at'])->isToday(), 'only today');
        }
    }

    public function test_another_family_cannot_read_the_activity(): void
    {
        $u = $this->demo();
        $ayan = $u->children()->first();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/children/{$ayan->id}/activity")->assertNotFound();
    }

    public function test_ads_are_only_for_free_parents_and_only_when_switched_on(): void
    {
        $free = User::factory()->create();
        Sanctum::actingAs($free);
        $this->assertNull($this->getJson('/api/v1/me')->json('ads'), 'off by default');
        config(['ads.enabled' => true, 'ads.units.banner_android' => 'ca-app-pub-test/1']);
        $ads = $this->getJson('/api/v1/me')->json('ads');
        $this->assertTrue($ads['child_directed']);
        $this->assertFalse($ads['personalized']);
        $this->assertTrue($ads['parent_app_only']);
        $free->update(['subscription_tier' => 'pro']);
        $this->assertNull($this->getJson('/api/v1/me')->json('ads'), 'Pro never sees ads');
        $free->update(['subscription_tier' => 'free', 'role' => 'super_admin']);
        $this->assertNull($this->getJson('/api/v1/me')->json('ads'), 'the owner never sees ads');
    }
}
