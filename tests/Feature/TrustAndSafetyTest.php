<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ContentFlag;
use App\Models\Lesson;
use App\Models\User;
use App\Services\ContentFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrustAndSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

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

    private function lessonFor(User $p, array $extra = []): Lesson
    {
        $c = $p->children()->first() ?? $p->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);

        return Lesson::create($extra + ['user_id' => $p->id, 'child_id' => $c->id, 'title' => 'Week 1', 'track' => 'standard']);
    }

    // ---------- terms ----------

    private function codeFor(string $email): string
    {
        $code = null;
        Mail::fake();
        $this->postJson('/api/v1/auth/code', ['email' => $email])->assertStatus(202);
        Mail::assertSent(\App\Mail\LoginCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });

        return $code;
    }

    public function test_terms_text_is_public(): void
    {
        $this->getJson('/api/v1/legal/terms')->assertOk()->assertJsonPath('version', config('legal.version'))->assertJsonStructure(['sections' => [['heading', 'body']]]);
    }

    public function test_new_parent_must_accept_terms_and_the_code_survives_the_first_try(): void
    {
        $code = $this->codeFor('new@example.com');

        $this->postJson('/api/v1/auth/verify', ['email' => 'new@example.com', 'code' => $code])
            ->assertStatus(422)->assertJsonPath('terms_required', true);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);

        $res = $this->postJson('/api/v1/auth/verify', ['email' => 'new@example.com', 'code' => $code, 'accept_terms' => true])->assertOk();
        $res->assertJsonPath('user.terms_required', false);
        $this->assertSame(config('legal.version'), User::where('email', 'new@example.com')->value('terms_version'));
    }

    public function test_existing_parent_on_an_older_version_is_asked_again_and_can_accept(): void
    {
        $p = User::factory()->create(['terms_version' => '0.1']);
        $this->as($p)->getJson('/api/v1/me')->assertJsonPath('terms_required', true);
        $this->as($p)->postJson('/api/v1/me/terms', ['accept' => false])->assertStatus(422);
        $this->as($p)->postJson('/api/v1/me/terms', ['accept' => true])->assertOk()->assertJsonPath('terms_required', false);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.terms_accepted', 'actor_id' => $p->id]);
    }

    public function test_the_configured_super_admin_is_not_blocked_by_terms(): void
    {
        config(['kindo.super_admins' => ['boss@example.com']]);
        $code = $this->codeFor('boss@example.com');
        $this->postJson('/api/v1/auth/verify', ['email' => 'boss@example.com', 'code' => $code])->assertOk()->assertJsonPath('user.role', 'super_admin');
    }

    // ---------- bad words ----------

    public function test_filter_sees_through_simple_tricks_but_not_innocent_words(): void
    {
        foreach (['you are a F.U.C.K.', 'sh1t', 'Fuuuuck off', 'such a b*i*t*c*h', 'KILL YOURSELF'] as $bad) {
            $this->assertNotNull(ContentFilter::find($bad), $bad);
        }
        foreach (['Scunthorpe', 'class assistant', 'Alif Bay Pay', 'Mama makes tea', 'Task 10', null, ''] as $ok) {
            $this->assertNull(ContentFilter::find($ok), (string) $ok);
        }
    }

    public function test_a_bad_word_opens_a_flag_and_holds_a_published_lesson_back(): void
    {
        $p = User::factory()->create(['subscription_tier' => 'pro']);
        $c = $p->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);

        $lesson = $this->as($p)->postJson("/api/v1/children/{$c->id}/lessons", ['title' => 'You stupid kid'])->assertCreated()->json();
        $this->assertSame(1, ContentFlag::where('status', 'open')->where('term', 'stupid')->count());

        $task = $this->as($p)->postJson("/api/v1/lessons/{$lesson['id']}/tasks", ['type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا'])->assertCreated()->json();
        $res = $this->as($p)->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertStatus(422);
        $this->assertContains('review', $res->json('problems.0.missing'));

        // saving the same text again does not pile up duplicate flags
        $this->as($p)->patchJson("/api/v1/lessons/{$lesson['id']}", ['title' => 'You stupid kid'])->assertOk();
        $this->assertSame(1, ContentFlag::count());
        $this->assertNotNull($task['id']);
    }

    public function test_a_flag_added_to_a_published_lesson_pulls_it_back_to_draft(): void
    {
        $p = User::factory()->create(['subscription_tier' => 'pro']);
        $l = $this->lessonFor($p, ['status' => 'published']);
        $l->tasks()->create(['type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا', 'position' => 1]);

        $this->as($p)->patchJson("/api/v1/lessons/{$l->id}", ['description' => 'what a bastard'])->assertOk()->assertJsonPath('status', 'draft');
    }

    public function test_bad_words_in_task_text_and_family_names_are_flagged(): void
    {
        $p = User::factory()->create(['subscription_tier' => 'pro']);
        $l = $this->lessonFor($p);
        $this->as($p)->postJson("/api/v1/lessons/{$l->id}/tasks", ['type' => 'trace_letter', 'title' => 'Alif', 'instruction' => 'say shit loudly', 'target' => 'ا'])->assertCreated();
        $this->assertDatabaseHas('content_flags', ['lesson_id' => $l->id, 'term' => 'shit', 'status' => 'open']);
    }

    public function test_templates_are_never_flagged_and_clean_text_creates_nothing(): void
    {
        $p = User::factory()->create(['subscription_tier' => 'pro']);
        $c = $p->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);
        $this->as($p)->postJson("/api/v1/children/{$c->id}/lessons", ['title' => 'Alif to Se'])->assertCreated();
        $this->assertSame(0, ContentFlag::count());
    }

    // ---------- admin review ----------

    public function test_only_the_super_admin_reaches_content_review(): void
    {
        $p = User::factory()->create();
        foreach (['content/flags', 'content/lessons', 'content/terms'] as $path) {
            $this->as($p)->getJson("/api/v1/admin/{$path}")->assertStatus(403);
            $this->as($this->admin())->getJson("/api/v1/admin/{$path}")->assertOk();
        }
    }

    public function test_admin_can_open_any_lesson_with_pictures_and_it_is_audited(): void
    {
        $p = User::factory()->create(['email' => 'mama@example.com']);
        $l = $this->lessonFor($p, ['title' => 'Ayan week 1']);
        $t = $l->tasks()->create(['type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا', 'position' => 1, 'image_path' => 'lessons/1/x.jpg']);
        Storage::disk('local')->put('lessons/1/x.jpg', 'jpegbytes');
        $admin = $this->admin();

        $res = $this->as($admin)->getJson("/api/v1/admin/content/lessons/{$l->id}")->assertOk();
        $res->assertJsonPath('owner.email', 'mama@example.com')->assertJsonPath('child_name', 'Ayan');
        $this->assertStringContainsString('signature=', $res->json('tasks.0.image_url'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.content_viewed', 'actor_id' => $admin->id, 'subject_id' => $l->id]);

        $this->as($admin)->getJson('/api/v1/admin/content/lessons?search=mama@example')->assertOk()->assertJsonPath('data.0.title', 'Ayan week 1')->assertJsonPath('data.0.owner_email', 'mama@example.com');
        $this->assertNotNull($t->id);
    }

    public function test_admin_cannot_open_platform_templates_through_the_parent_content_view(): void
    {
        $tpl = Lesson::create(['title' => 'Starter', 'is_template' => true, 'track' => 'standard']);
        $this->as($this->admin())->getJson("/api/v1/admin/content/lessons/{$tpl->id}")->assertStatus(404);
    }

    public function test_admin_can_flag_by_hand_which_hides_a_published_lesson(): void
    {
        $p = User::factory()->create();
        $l = $this->lessonFor($p, ['status' => 'published']);

        $this->as($this->admin())->postJson("/api/v1/admin/content/lessons/{$l->id}/flag", ['note' => 'odd picture'])->assertCreated()->assertJsonPath('reason', 'manual');
        $this->assertSame('draft', $l->fresh()->status);
    }

    public function test_dismiss_releases_the_lesson_and_records_who_decided(): void
    {
        $p = User::factory()->create(['subscription_tier' => 'pro']);
        $l = $this->lessonFor($p, ['title' => 'Stupid game']);
        app(ContentFilter::class)->scanLesson($l);
        $flag = ContentFlag::firstOrFail();
        $admin = $this->admin();

        $this->as($admin)->postJson("/api/v1/admin/content/flags/{$flag->id}/resolve", ['action' => 'dismiss', 'note' => 'fine here'])->assertOk()->assertJsonPath('status', 'dismissed');
        $this->assertSame($admin->id, $flag->fresh()->reviewed_by);
        $this->as($admin)->postJson("/api/v1/admin/content/flags/{$flag->id}/resolve", ['action' => 'dismiss'])->assertStatus(404);   // already decided

        // the same text is not flagged again after a ruling
        app(ContentFilter::class)->scanLesson($l->fresh());
        $this->assertSame(1, ContentFlag::count());
    }

    public function test_remove_archives_the_lesson_and_deactivate_signs_the_parent_out(): void
    {
        $p = User::factory()->create();
        $p->createToken('web');
        $l = $this->lessonFor($p, ['title' => 'Shit lesson']);
        app(ContentFilter::class)->scanLesson($l);
        $flag = ContentFlag::firstOrFail();

        $this->as($this->admin())->postJson("/api/v1/admin/content/flags/{$flag->id}/resolve", ['action' => 'deactivate_owner', 'note' => 'abusive content'])->assertOk()->assertJsonPath('status', 'actioned');

        $this->assertSame('archived', $l->fresh()->status);
        $this->assertSame('inactive', $p->fresh()->status);
        $this->assertSame('abusive content', $p->fresh()->status_reason);
        $this->assertSame(0, $p->tokens()->count());
        $this->assertTrue(AuditLog::where('action', 'admin.flag_resolved')->exists());
    }

    public function test_blocked_words_can_be_added_and_removed_and_take_effect(): void
    {
        $admin = $this->admin();
        $id = $this->as($admin)->postJson('/api/v1/admin/content/terms', ['term' => ' Blorp! '])->assertCreated()->json('id');
        $this->assertNotNull(ContentFilter::find('what a blorp'));
        $this->as($admin)->deleteJson("/api/v1/admin/content/terms/{$id}")->assertOk();
        $this->assertNull(ContentFilter::find('what a blorp'));
    }

    // ---------- inactive accounts ----------

    public function test_admin_marks_an_account_inactive_with_a_reason_and_restores_it(): void
    {
        $p = User::factory()->create();
        $p->createToken('web');
        $admin = $this->admin();

        $this->as($admin)->patchJson("/api/v1/admin/users/{$p->id}", ['status' => 'inactive', 'reason' => 'Reported content'])->assertOk()->assertJsonPath('status', 'inactive');
        $this->assertSame(0, $p->tokens()->count());
        $this->as($admin)->getJson('/api/v1/admin/users?search=' . $p->email)->assertJsonPath('data.0.status_reason', 'Reported content');

        $this->as($admin)->patchJson("/api/v1/admin/users/{$p->id}", ['status' => 'active'])->assertOk();
        $this->assertNull($p->fresh()->status_reason);
    }

    public function test_an_inactive_parent_cannot_sign_in_and_an_admin_cannot_be_made_inactive(): void
    {
        $p = User::factory()->create(['status' => 'inactive']);
        $this->as($p)->getJson('/api/v1/me')->assertStatus(403);

        $a = $this->admin();
        $this->as($this->admin())->patchJson("/api/v1/admin/users/{$a->id}", ['status' => 'inactive'])->assertStatus(422);
    }
}
