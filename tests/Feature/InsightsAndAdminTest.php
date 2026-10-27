<?php

namespace Tests\Feature;

use App\Models\AiDiagnosticReport;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\Lesson;
use App\Models\PracticeMistakeLog;
use App\Models\PracticeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InsightsAndAdminTest extends TestCase
{
    use RefreshDatabase;

    private function parentWithChild(): array
    {
        $p = User::factory()->create();
        $c = $p->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);

        return [$p, $c];
    }

    /** An unanswered alert created N minutes ago (created_at is not mass-assignable on purpose). */
    private function old(Child $c, string $need, int $minutesAgo): ChildNeedsLog
    {
        $log = ChildNeedsLog::create(['child_id' => $c->id, 'need_type' => $need]);
        $log->forceFill(['created_at' => now()->subMinutes($minutesAgo)])->save();

        return $log;
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    // ---------- alert replies ----------

    public function test_alert_push_reads_naturally_and_lifecycle_goes_sent_seen_on_the_way(): void
    {
        Http::fake();
        [$p, $c] = $this->parentWithChild();
        $this->as($p);
        $id = $this->postJson('/api/v1/child/alert', ['child_id' => $c->id, 'need_type' => 'potty'])->assertOk()->json('id');
        $this->assertSame('sent', $this->getJson("/api/v1/needs/{$id}")->json('status'));

        $this->postJson("/api/v1/needs/{$id}/seen")->assertOk()->assertJsonPath('status', 'seen');
        $seenAt = ChildNeedsLog::find($id)->seen_at;
        $this->travel(2)->minutes();
        $this->postJson("/api/v1/needs/{$id}/seen");                                             // idempotent
        $this->assertEquals($seenAt, ChildNeedsLog::find($id)->seen_at);

        $res = $this->postJson("/api/v1/needs/{$id}/acknowledge")->assertOk();
        $res->assertJsonPath('status', 'on_the_way')->assertJsonPath('replied_by', $p->name);
        $this->assertGreaterThanOrEqual(120, $res->json('reply_seconds'));
        $this->assertSame($p->id, ChildNeedsLog::find($id)->acknowledged_by);
    }

    public function test_push_phrase_is_used(): void
    {
        $this->assertSame('needs the potty', \App\Http\Controllers\Api\NeedsController::PHRASES['potty']);
        $this->assertSame('is hungry', \App\Http\Controllers\Api\NeedsController::PHRASES['food']);
    }

    public function test_acknowledging_marks_seen_too_and_a_stranger_cannot_reply(): void
    {
        [$p, $c] = $this->parentWithChild();
        $log = ChildNeedsLog::create(['child_id' => $c->id, 'need_type' => 'water']);

        $this->as(User::factory()->create())->postJson("/api/v1/needs/{$log->id}/acknowledge")->assertNotFound();
        $this->as($p)->postJson("/api/v1/needs/{$log->id}/acknowledge")->assertOk();
        $this->assertNotNull($log->fresh()->seen_at);
    }

    // ---------- parent insights ----------

    public function test_summary_adds_up_practice_alerts_and_lessons(): void
    {
        [$p, $c] = $this->parentWithChild();
        $s1 = PracticeSession::create(['child_id' => $c->id, 'language' => 'urdu', 'total_attempts' => 10, 'successful_attempts' => 8, 'duration_seconds' => 600]);
        PracticeSession::create(['child_id' => $c->id, 'language' => 'english', 'total_attempts' => 10, 'successful_attempts' => 5, 'duration_seconds' => 300]);
        PracticeMistakeLog::create(['session_id' => $s1->id, 'target_string' => 'ب', 'attempted_string' => 'پ', 'mistake_type' => 'dot_misplacement', 'stroke_deviation_score' => 10]);
        PracticeMistakeLog::create(['session_id' => $s1->id, 'target_string' => 'ب', 'attempted_string' => 'ت', 'mistake_type' => 'dot_misplacement', 'stroke_deviation_score' => 20]);
        ChildNeedsLog::create(['child_id' => $c->id, 'need_type' => 'potty', 'acknowledged' => true, 'acknowledged_at' => now()->addSeconds(90)]);
        ChildNeedsLog::create(['child_id' => $c->id, 'need_type' => 'potty']);
        Lesson::create(['user_id' => $p->id, 'child_id' => $c->id, 'title' => 'L', 'status' => 'published']);
        Lesson::create(['user_id' => $p->id, 'child_id' => $c->id, 'title' => 'D', 'status' => 'draft']);

        $r = $this->as($p)->getJson("/api/v1/children/{$c->id}/insights/summary?days=7")->assertOk();

        $r->assertJsonPath('practice.minutes', 15)->assertJsonPath('practice.accuracy_pct', 65)->assertJsonPath('practice.sessions', 2)
            ->assertJsonPath('practice.by_language.urdu.accuracy_pct', 80)->assertJsonPath('practice.mistake_types.dot_misplacement', 2)
            ->assertJsonPath('practice.hardest_targets.ب', 2)->assertJsonPath('practice.avg_stroke_deviation', 15)
            ->assertJsonPath('needs.total', 2)->assertJsonPath('needs.answered', 1)->assertJsonPath('needs.unanswered', 1)
            ->assertJsonPath('needs.avg_reply_seconds', 90)->assertJsonPath('needs.by_type.potty', 2)
            ->assertJsonPath('lessons.published', 1)->assertJsonPath('lessons.drafts', 1);
    }

    public function test_summary_is_empty_but_valid_for_a_new_child_and_respects_ownership(): void
    {
        [$p, $c] = $this->parentWithChild();

        $this->as($p)->getJson("/api/v1/children/{$c->id}/insights/summary")->assertOk()->assertJsonPath('practice.accuracy_pct', null)->assertJsonPath('needs.avg_reply_seconds', null);
        $this->as(User::factory()->create())->getJson("/api/v1/children/{$c->id}/insights/summary")->assertNotFound();
    }

    public function test_daily_series_has_one_row_per_day_with_zeros(): void
    {
        [$p, $c] = $this->parentWithChild();
        PracticeSession::create(['child_id' => $c->id, 'language' => 'urdu', 'total_attempts' => 4, 'successful_attempts' => 3, 'duration_seconds' => 120]);

        $rows = $this->as($p)->getJson("/api/v1/children/{$c->id}/insights/daily?days=5")->assertOk()->json();

        $this->assertCount(5, $rows);
        $this->assertSame(2, collect($rows)->sum('minutes'));
        $this->assertSame(4, collect($rows)->where('minutes', 0)->count());
    }

    public function test_csv_export_downloads_and_neutralises_spreadsheet_formulas(): void
    {
        [$p, $c] = $this->parentWithChild();
        ChildNeedsLog::create(['child_id' => $c->id, 'need_type' => '=HYPERLINK("http://evil")']);
        PracticeSession::create(['child_id' => $c->id, 'language' => 'urdu', 'total_attempts' => 4, 'successful_attempts' => 3, 'duration_seconds' => 120]);

        $res = $this->as($p)->get("/api/v1/children/{$c->id}/insights/export.csv")->assertOk();
        $csv = $res->streamedContent();

        $this->assertStringContainsString('practice', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    // ---------- super admin ----------

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    public function test_admin_endpoints_are_closed_to_parents_and_open_to_the_super_admin(): void
    {
        foreach (['overview', 'usage', 'quality', 'alerts', 'ai-reports', 'health', 'users', 'audit'] as $path) {
            $this->as(User::factory()->create())->getJson("/api/v1/admin/{$path}")->assertStatus(403);
            $this->as($this->admin())->getJson("/api/v1/admin/{$path}")->assertOk();
        }
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/admin/overview')->assertStatus(401);
    }

    public function test_overview_counts_the_platform(): void
    {
        [$p, $c] = $this->parentWithChild();
        $p->update(['subscription_tier' => 'pro', 'last_login_at' => now()]);
        $c->update(['track' => 'special']);
        User::factory()->create(['status' => 'suspended']);
        Lesson::create(['user_id' => $p->id, 'child_id' => $c->id, 'title' => 'L', 'status' => 'published']);
        ChildNeedsLog::create(['child_id' => $c->id, 'need_type' => 'potty', 'acknowledged' => true, 'acknowledged_at' => now()->addSeconds(60)]);
        $this->old($c, 'food', 30);

        $r = $this->as($this->admin())->getJson('/api/v1/admin/overview')->assertOk();

        $r->assertJsonPath('accounts.total', 3)->assertJsonPath('accounts.pro', 1)->assertJsonPath('accounts.suspended', 1)->assertJsonPath('accounts.active_7d', 1)
            ->assertJsonPath('children.total', 1)->assertJsonPath('children.special_track', 1)->assertJsonPath('lessons.published', 1)
            ->assertJsonPath('alerts_7d.total', 2)->assertJsonPath('alerts_7d.avg_reply_seconds', 60)->assertJsonPath('alerts_7d.unanswered_over_15m', 1);
    }

    public function test_admin_reports_never_expose_child_names_or_media(): void
    {
        [$p, $c] = $this->parentWithChild();
        $this->old($c, 'potty', 40);

        $this->as($this->admin());
        foreach (['overview', 'alerts', 'quality', 'users'] as $path) {
            $body = $this->getJson("/api/v1/admin/{$path}")->assertOk()->getContent();
            $this->assertStringNotContainsString('Ayan', $body, "{$path} leaked a child name");
            $this->assertStringNotContainsString('image_url', $body);
            $this->assertStringNotContainsString('audio_url', $body);
        }
        $this->assertSame(40, $this->getJson('/api/v1/admin/alerts')->json('unanswered_over_15m.0.minutes_waiting'));
    }

    public function test_quality_report_finds_tasks_missing_required_sound(): void
    {
        [$p, $c] = $this->parentWithChild();
        $l = Lesson::create(['user_id' => $p->id, 'child_id' => $c->id, 'title' => 'D', 'status' => 'draft']);
        $l->tasks()->create(['position' => 1, 'type' => 'spell_word', 'title' => 'Cat', 'target' => 'cat']);
        $l->tasks()->create(['position' => 2, 'type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا']);

        $r = $this->as($this->admin())->getJson('/api/v1/admin/quality')->assertOk();

        $r->assertJsonPath('tasks_missing_required_sound', 1)->assertJsonPath('draft_lessons', 1)->assertJsonPath('draft_lessons_blocked', 1)->assertJsonPath('tasks_by_type.spell_word', 1);
    }

    public function test_ai_report_provider_is_recorded_and_counted(): void
    {
        config(['kindo.groq_keys' => ['g1'], 'kindo.gemini_keys' => []]);
        \Illuminate\Support\Facades\Cache::flush();
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['phonetic_blindspots' => [], 'custom_exercises' => []])]]]])]);
        [$p, $c] = $this->parentWithChild();

        (new \App\Jobs\GeneratePediatricReportJob($c->id))->handle(app(\App\Services\AIServiceManager::class));

        $this->assertSame('groq', AiDiagnosticReport::first()->provider);
        $this->as($this->admin())->getJson('/api/v1/admin/ai-reports')->assertOk()->assertJsonPath('generated_7d', 1)->assertJsonPath('by_provider_7d.groq', 1);
    }

    public function test_admin_can_search_suspend_and_change_plan_and_it_is_audited(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['email' => 'target@example.com']);
        $token = $target->createToken('web')->plainTextToken;
        $this->as($admin);

        $this->getJson('/api/v1/admin/users?search=target')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.email', 'target@example.com');

        $this->patchJson("/api/v1/admin/users/{$target->id}", ['status' => 'suspended', 'plan' => 'pro'])->assertOk()->assertJsonPath('status', 'suspended')->assertJsonPath('plan', 'pro');
        $this->assertSame(0, $target->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user_updated', 'actor_id' => $admin->id, 'subject_id' => $target->id]);

        $this->patchJson("/api/v1/admin/users/{$target->id}", ['status' => 'active'])->assertOk();
        $this->patchJson("/api/v1/admin/users/{$admin->id}", ['status' => 'suspended'])->assertStatus(422);           // cannot lock yourself out
        $this->patchJson("/api/v1/admin/users/{$target->id}", ['status' => 'banned'])->assertStatus(422);
    }

    public function test_audit_log_can_be_filtered(): void
    {
        $admin = $this->admin();
        AuditLog::record($admin, 'auth.login');
        AuditLog::record($admin, 'lesson.published');

        $r = $this->as($admin)->getJson('/api/v1/admin/audit?action=lesson')->assertOk();

        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame('lesson.published', $r->json('data.0.action'));
    }

    public function test_health_panel_reports_scheduler_heartbeat(): void
    {
        $this->as($this->admin())->getJson('/api/v1/admin/health')->assertOk()->assertJsonPath('database', true)->assertJsonPath('scheduler.alive', false);

        \Illuminate\Support\Facades\Cache::put('scheduler:last_run', now()->toIso8601String(), 600);
        $this->as($this->admin())->getJson('/api/v1/admin/health')->assertJsonPath('scheduler.alive', true);
    }
}
