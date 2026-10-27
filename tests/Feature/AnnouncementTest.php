<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementTest extends TestCase
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

    public function test_only_the_super_admin_manages_announcements(): void
    {
        $this->as(User::factory()->create())->getJson('/api/v1/admin/announcements')->assertStatus(403);
        $this->postJson('/api/v1/admin/announcements', ['title' => 'x', 'body' => 'y'])->assertStatus(403);
        $this->as($this->admin())->postJson('/api/v1/admin/announcements', ['title' => 'Maintenance', 'body' => 'Back at 6pm.'])->assertCreated()->assertJsonPath('level', 'info');
        $this->assertTrue(\App\Models\AuditLog::where('action', 'admin.announcement_created')->exists());
    }

    public function test_a_parent_sees_live_announcements_important_first(): void
    {
        $admin = $this->admin();
        $this->as($admin)->postJson('/api/v1/admin/announcements', ['title' => 'New games', 'body' => 'Try them.'])->assertCreated();
        $this->postJson('/api/v1/admin/announcements', ['title' => 'Downtime tonight', 'body' => 'From 2am.', 'level' => 'important'])->assertCreated();

        $titles = $this->as(User::factory()->create())->getJson('/api/v1/announcements')->assertOk()->json();
        $this->assertSame(['Downtime tonight', 'New games'], array_column($titles, 'title'));
    }

    public function test_scheduled_expired_and_switched_off_ones_stay_hidden(): void
    {
        $admin = $this->admin();
        $this->as($admin);
        $this->postJson('/api/v1/admin/announcements', ['title' => 'Later', 'body' => 'b', 'starts_at' => now()->addDay()->toIso8601String()])->assertCreated();
        Announcement::create(['title' => 'Over', 'body' => 'b', 'ends_at' => now()->subHour()]);
        Announcement::create(['title' => 'Off', 'body' => 'b', 'active' => false]);
        $now = $this->postJson('/api/v1/admin/announcements', ['title' => 'Now', 'body' => 'b', 'starts_at' => now()->subHour()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String()])->assertCreated()->json('id');

        $this->as(User::factory()->create());
        $this->assertSame(['Now'], array_column($this->getJson('/api/v1/announcements')->json(), 'title'));

        $all = $this->as($admin)->getJson('/api/v1/admin/announcements')->json();
        $live = collect($all)->where('live', true)->pluck('title')->all();
        $this->assertSame(['Now'], $live);
        $this->assertNotNull($now);
    }

    public function test_dismissing_hides_it_for_that_parent_only_and_counts(): void
    {
        $id = $this->as($this->admin())->postJson('/api/v1/admin/announcements', ['title' => 'Hello', 'body' => 'b'])->json('id');
        $mama = User::factory()->create();
        $baba = User::factory()->create();

        $this->as($mama)->postJson("/api/v1/announcements/{$id}/dismiss")->assertOk();
        $this->postJson("/api/v1/announcements/{$id}/dismiss")->assertOk();                      // twice is fine
        $this->assertSame([], $this->getJson('/api/v1/announcements')->json());
        $this->as($baba);
        $this->assertCount(1, $this->getJson('/api/v1/announcements')->json());

        $row = collect($this->as($this->admin())->getJson('/api/v1/admin/announcements')->json())->firstWhere('id', $id);
        $this->assertSame(1, $row['dismissed_by']);
        $this->as($mama)->postJson('/api/v1/announcements/9999/dismiss')->assertStatus(404);
    }

    public function test_the_end_must_come_after_the_start_and_edits_are_validated_before_saving(): void
    {
        $this->as($this->admin());
        $this->postJson('/api/v1/admin/announcements', ['title' => 'x', 'body' => 'y', 'starts_at' => '2026-05-02', 'ends_at' => '2026-05-01'])->assertStatus(422);
        $id = $this->postJson('/api/v1/admin/announcements', ['title' => 'x', 'body' => 'y', 'starts_at' => '2026-05-01', 'ends_at' => '2026-05-09'])->json('id');
        $this->patchJson("/api/v1/admin/announcements/{$id}", ['ends_at' => '2026-04-01'])->assertStatus(422);
        $this->assertSame('2026-05-09', Announcement::find($id)->ends_at->format('Y-m-d'));      // the bad edit was not saved
        $this->postJson('/api/v1/admin/announcements', ['title' => '', 'body' => 'y'])->assertStatus(422);
        $this->postJson('/api/v1/admin/announcements', ['title' => 'x', 'body' => 'y', 'level' => 'loud'])->assertStatus(422);
    }

    public function test_the_admin_can_end_and_remove_one(): void
    {
        $this->as($this->admin());
        $id = $this->postJson('/api/v1/admin/announcements', ['title' => 'Bye', 'body' => 'b'])->json('id');
        $this->patchJson("/api/v1/admin/announcements/{$id}", ['active' => false])->assertOk()->assertJsonPath('active', false);
        $this->as(User::factory()->create());
        $this->assertSame([], $this->getJson('/api/v1/announcements')->json());
        $this->as($this->admin())->deleteJson("/api/v1/admin/announcements/{$id}")->assertOk();
        $this->assertNull(Announcement::find($id));
    }

    public function test_a_login_is_needed(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/announcements')->assertStatus(401);
    }
}
