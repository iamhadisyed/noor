<?php

namespace Tests\Feature;

use App\Jobs\GeneratePediatricReportJob;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\DeviceToken;
use App\Models\PracticeMistakeLog;
use App\Models\PracticeSession;
use App\Models\User;
use App\Services\AIServiceManager;
use Database\Seeders\AacCardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KindoApiTest extends TestCase
{
    use RefreshDatabase;

    private function child(User $user): Child
    {
        return $user->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);
    }

    public function test_free_tier_is_limited_to_one_child(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $this->child($user);

        $this->postJson('/api/v1/children', ['name' => 'B', 'date_of_birth' => '2020-01-01'])->assertStatus(402);
    }

    public function test_alert_logs_need_and_is_scoped_to_owner(): void
    {
        Http::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $child = $this->child($user);

        $this->postJson('/api/v1/child/alert', ['child_id' => $child->id, 'need_type' => 'potty'])->assertOk();
        $this->assertSame(1, ChildNeedsLog::where('child_id', $child->id)->count());

        $other = User::factory()->create();
        $stranger = $this->child($other);
        $this->postJson('/api/v1/child/alert', ['child_id' => $stranger->id, 'need_type' => 'potty'])->assertNotFound();
    }

    public function test_fcm_payload_is_high_priority_and_critical_on_ios(): void
    {
        $svc = new \App\Services\FcmService();
        $android = $svc->payload(new DeviceToken(['token' => 't', 'device_type' => 'android']), 'a', 'b', ['log_id' => 1]);
        $ios = $svc->payload(new DeviceToken(['token' => 't', 'device_type' => 'ios']), 'a', 'b', []);

        $this->assertSame('HIGH', $android['android']['priority']);
        $this->assertSame('urgent_needs_channel', $android['android']['notification']['channel_id']);
        $this->assertSame('1', $android['data']['log_id']);
        $this->assertSame('critical', $ios['apns']['payload']['aps']['interruption-level']);
    }

    public function test_ai_manager_fails_over_from_rate_limited_groq_to_gemini(): void
    {
        config(['kindo.groq_keys' => ['g1'], 'kindo.gemini_keys' => ['m1']]);
        Cache::flush();
        Http::fake([
            'api.groq.com/*' => Http::response([], 429),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"phonetic_blindspots":[]}']]]]],
            ]),
        ]);

        $result = app(AIServiceManager::class)->generateDiagnosticReport(['x' => 1]);

        $this->assertSame(['phonetic_blindspots' => [], '_provider' => 'gemini'], $result);   // Groq was rate limited, so Gemini answered
        $this->assertTrue(Cache::has('cooldown_groq_0'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage') && $r->hasHeader('x-goog-api-key', 'm1') && ! str_contains($r->url(), 'key='));
    }

    public function test_ai_manager_returns_null_when_all_keys_fail(): void
    {
        config(['kindo.groq_keys' => ['g1'], 'kindo.gemini_keys' => []]);
        Cache::flush();
        Http::fake(['*' => Http::response([], 500)]);

        $this->assertNull(app(AIServiceManager::class)->generateDiagnosticReport(['y' => 1]));
    }

    public function test_report_job_stores_report_and_is_idempotent_per_week(): void
    {
        config(['kindo.groq_keys' => ['g1'], 'kindo.gemini_keys' => []]);
        Cache::flush();
        Http::fake(['api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'phonetic_blindspots' => [['issue' => 'b/p', 'frequency' => 3, 'root_cause' => 'x']],
            'potty_routine_summary' => 'regular', 'sensory_behavior_analysis' => 'calm',
            'parent_tips' => ['t'], 'dietary_chaining_tips' => ['d'], 'custom_exercises' => [],
        ])]]]])]);

        $child = $this->child(User::factory()->create());
        $s = PracticeSession::create(['child_id' => $child->id, 'language' => 'english', 'total_attempts' => 2, 'successful_attempts' => 1, 'duration_seconds' => 30]);
        PracticeMistakeLog::create(['session_id' => $s->id, 'target_string' => 'bat', 'attempted_string' => 'pat', 'mistake_type' => 'phonetic_substitution']);

        (new GeneratePediatricReportJob($child->id))->handle(app(AIServiceManager::class));
        (new GeneratePediatricReportJob($child->id))->handle(app(AIServiceManager::class));

        $this->assertSame(1, $child->reports()->count());
        $report = $child->reports()->first();
        $this->assertSame('calm', $report->sensory_behavior_summary);
        $this->assertSame(['d'], $report->dietary_chaining_tips);
        Http::assertSent(fn ($r) => str_contains($r->body(), 'child_age_years\\":5'));
    }

    public function test_reports_require_pro_and_dispatch_job(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $child = $this->child($user);

        $this->postJson("/api/v1/children/{$child->id}/reports")->assertStatus(402);

        $user->update(['subscription_tier' => 'pro']);
        $this->postJson("/api/v1/children/{$child->id}/reports")->assertStatus(202);
        Queue::assertPushed(GeneratePediatricReportJob::class);
    }

    public function test_pecs_pdf_downloads_for_free_tier(): void
    {
        $this->seed(AacCardSeeder::class);
        Sanctum::actingAs(User::factory()->create());

        $res = $this->get('/api/v1/pecs.pdf');

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_urdu_label_renders_as_png_data_uri(): void
    {
        $uri = \App\Support\UrduText::imageDataUri('گلے لگاؤ');

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')));
        [$w, $h] = getimagesizefromstring($png);
        $this->assertGreaterThan($h, $w); // a wide label, not an empty box
    }

    public function test_session_sync_stores_mistakes(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $child = $this->child($user);

        $this->postJson("/api/v1/children/{$child->id}/sessions", [
            'language' => 'urdu', 'total_attempts' => 3, 'successful_attempts' => 2, 'duration_seconds' => 60,
            'mistakes' => [['target_string' => 'ب', 'attempted_string' => 'پ', 'mistake_type' => 'dot_misplacement']],
        ])->assertCreated();

        $this->assertSame(1, PracticeMistakeLog::count());
    }
}
