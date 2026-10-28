<?php

namespace Tests\Feature;

use App\Models\AacCard;
use App\Models\Assignment;
use App\Models\ChildNeedsLog;
use App\Models\FamilyMember;
use App\Models\GameSession;
use App\Models\Lesson;
use App\Models\SpecialistAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Every route that takes someone's record by id, tried by a signed-in stranger with Pro and a valid body (so a
 * validation error cannot hide a missing ownership check). Each must answer 403 or 404 and leave the record as it was.
 */
class OwnershipSweepTest extends TestCase
{
    use RefreshDatabase;

    private User $victim;
    private User $stranger;
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->victim = User::factory()->create(['subscription_tier' => 'pro']);
        $this->stranger = User::factory()->create(['subscription_tier' => 'pro']);

        $child = $this->victim->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);
        $lesson = Lesson::create(['user_id' => $this->victim->id, 'child_id' => $child->id, 'title' => 'Week 1', 'track' => 'standard', 'status' => 'draft', 'week_number' => 1, 'is_template' => false]);
        $task = $lesson->tasks()->create(['position' => 1, 'type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا']);
        $family = FamilyMember::create(['child_id' => $child->id, 'relation_title' => 'Mama', 'display_name' => 'Mama', 'photo_path' => 'family/1/p.jpg', 'audio_prompt_path' => 'family/1/a.mp3']);
        $card = AacCard::create(['child_id' => $child->id, 'category' => 'activities', 'english_label' => 'Car', 'urdu_label' => 'Car', 'icon_path' => 'aac/1/c.jpg']);
        $need = ChildNeedsLog::create(['child_id' => $child->id, 'need_type' => 'water']);
        $access = SpecialistAccess::create(['child_id' => $child->id, 'role' => 'therapist', 'label' => 'Ms Sara', 'status' => 'active', 'created_by' => $this->victim->id]);
        $assign = Assignment::create(['child_id' => $child->id, 'created_by' => $this->victim->id, 'title' => 'Trace', 'status' => 'open']);
        $game = GameSession::create(['child_id' => $child->id, 'game' => 'count', 'level' => 1, 'allowed_seconds' => 300, 'status' => 'open']);
        $template = Lesson::create(['user_id' => null, 'child_id' => null, 'title' => 'Starter', 'track' => 'standard', 'status' => 'published', 'week_number' => 1, 'is_template' => true]);

        $this->ids = ['child' => $child->id, 'lesson' => $lesson->id, 'task' => $task->id, 'family' => $family->id, 'card' => $card->id, 'need' => $need->id,
            'access' => $access->id, 'assign' => $assign->id, 'game' => $game->id, 'template' => $template->id];
    }

    private function routes(): array
    {
        $i = $this->ids;
        $photo = fn () => UploadedFile::fake()->image('p.jpg', 100, 100);

        return [
            ['GET', "children/{$i['child']}"], ['PATCH', "children/{$i['child']}", ['name' => 'Hacked']], ['DELETE', "children/{$i['child']}"],
            ['GET', "children/{$i['child']}/lessons"], ['POST', "children/{$i['child']}/lessons", ['title' => 'x']],
            ['POST', "children/{$i['child']}/lessons/from-template/{$i['template']}"],
            ['GET', "children/{$i['child']}/family"], ['GET', "children/{$i['child']}/assignments"], ['GET', "children/{$i['child']}/needs"],
            ['GET', "children/{$i['child']}/insights/summary"], ['GET', "children/{$i['child']}/insights/daily"], ['GET', "children/{$i['child']}/insights/export.csv"],
            ['GET', "children/{$i['child']}/insights/report.pdf"], ['GET', "children/{$i['child']}/letters"], ['GET', "children/{$i['child']}/milestones"],
            ['GET', "children/{$i['child']}/milestones/streak_3/card.png"], ['GET', "children/{$i['child']}/rewards"],
            ['POST', "children/{$i['child']}/rewards/bonus", ['minutes' => 30]], ['PATCH', "children/{$i['child']}/rewards/settings", ['enabled' => false]],
            ['POST', "children/{$i['child']}/games/count/start", ['rounds' => 3]], ['POST', "children/{$i['child']}/reports"],
            ['GET', "children/{$i['child']}/reports/latest"], ['GET', "children/{$i['child']}/specialists"],
            ['POST', "children/{$i['child']}/specialists", ['role' => 'teacher', 'label' => 'x']],
            ['POST', "children/{$i['child']}/sessions", ['language' => 'urdu', 'total_attempts' => 5, 'successful_attempts' => 5, 'duration_seconds' => 600]],
            ['POST', "children/{$i['child']}/aac-cards", ['english_label' => 'x', 'category' => 'food', 'icon' => $photo()]],
            ['POST', "children/{$i['child']}/lessons/import", ['photo' => $photo()]],
            ['GET', "lessons/{$i['lesson']}"], ['PATCH', "lessons/{$i['lesson']}", ['title' => 'Hacked']], ['DELETE', "lessons/{$i['lesson']}"],
            ['POST', "lessons/{$i['lesson']}/duplicate", ['child_id' => $i['child']]], ['POST', "lessons/{$i['lesson']}/publish"], ['POST', "lessons/{$i['lesson']}/unpublish"],
            ['GET', "lessons/{$i['lesson']}/readiness"], ['POST', "lessons/{$i['lesson']}/tasks", ['type' => 'trace_letter', 'title' => 'x', 'target' => 'ب']],
            ['POST', "lessons/{$i['lesson']}/tasks/reorder", ['ids' => [$i['task']]]],
            ['PATCH', "tasks/{$i['task']}", ['title' => 'Hacked']], ['DELETE', "tasks/{$i['task']}"], ['POST', "tasks/{$i['task']}/media", ['kind' => 'image', 'file' => $photo()]],
            ['DELETE', "tasks/{$i['task']}/media/image"],
            ['POST', "family/{$i['family']}", ['display_name' => 'Hacked']], ['PATCH', "family/{$i['family']}", ['display_name' => 'Hacked']], ['DELETE', "family/{$i['family']}"],
            ['POST', "aac-cards/{$i['card']}", ['english_label' => 'Hacked']], ['PATCH', "aac-cards/{$i['card']}", ['english_label' => 'Hacked']], ['DELETE', "aac-cards/{$i['card']}"],
            ['GET', "needs/{$i['need']}"], ['POST', "needs/{$i['need']}/seen"], ['POST', "needs/{$i['need']}/acknowledge"],
            ['POST', "specialists/{$i['access']}/new-code"], ['DELETE', "specialists/{$i['access']}"],
            ['POST', "assignments/{$i['assign']}/done"], ['POST', "game-sessions/{$i['game']}/finish", ['rounds_played' => 3, 'correct' => 3, 'seconds_played' => 60]],
            // the specialist side: a stranger who was never given a code
            ['GET', "specialist/children/{$i['child']}/summary"], ['GET', "specialist/children/{$i['child']}/assignments"],
            ['POST', "specialist/children/{$i['child']}/assignments", ['title' => 'x']], ['DELETE', "specialist/children/{$i['child']}"],
            ['PATCH', "specialist/assignments/{$i['assign']}", ['title' => 'Hacked']], ['DELETE', "specialist/assignments/{$i['assign']}"],
        ];
    }

    public function test_a_stranger_cannot_read_or_change_anyone_elses_records(): void
    {
        Sanctum::actingAs($this->stranger);
        $wrong = [];

        foreach ($this->routes() as $route) {
            [$method, $uri] = $route;
            $body = $route[2] ?? [];
            $files = array_filter($body, fn ($v) => $v instanceof UploadedFile);
            $fields = array_diff_key($body, $files);
            $r = $this->call($method, "/api/v1/{$uri}", $fields, [], $files, ['HTTP_ACCEPT' => 'application/json']);
            if (! in_array($r->getStatusCode(), [403, 404], true)) {
                $wrong[] = "{$method} {$uri} gave {$r->getStatusCode()}";
            }
        }

        $this->assertSame([], $wrong);

        // and nothing of the victim's changed
        $this->assertSame('Ayan', $this->victim->children()->first()->name);
        $this->assertSame('Week 1', Lesson::find($this->ids['lesson'])->title);
        $this->assertSame('draft', Lesson::find($this->ids['lesson'])->status);
        $this->assertSame('Alif', \App\Models\LessonTask::find($this->ids['task'])->title);
        $this->assertSame('Mama', FamilyMember::find($this->ids['family'])->display_name);
        $this->assertSame('Car', AacCard::find($this->ids['card'])->english_label);
        $this->assertSame('active', SpecialistAccess::find($this->ids['access'])->status);
        $this->assertSame('open', Assignment::find($this->ids['assign'])->status);
        $this->assertNull(ChildNeedsLog::find($this->ids['need'])->seen_at);
        $this->assertSame('open', GameSession::find($this->ids['game'])->status);
        $this->assertSame(0, \App\Models\RewardLedger::count());
        $this->assertSame(0, $this->stranger->children()->count());
    }

    public function test_the_same_routes_work_for_the_owner_so_the_sweep_is_not_vacuous(): void
    {
        Sanctum::actingAs($this->victim);

        $this->getJson("/api/v1/children/{$this->ids['child']}")->assertOk();
        $this->getJson("/api/v1/lessons/{$this->ids['lesson']}")->assertOk();
        $this->getJson("/api/v1/needs/{$this->ids['need']}")->assertOk();
        $this->getJson("/api/v1/children/{$this->ids['child']}/milestones")->assertOk();
        $this->getJson("/api/v1/children/{$this->ids['child']}/specialists")->assertOk();
    }

    public function test_signed_media_links_do_not_work_unsigned_or_for_the_wrong_record(): void
    {
        Sanctum::actingAs($this->stranger);

        foreach (['media/'.$this->ids['task'].'/image', 'media/family/'.$this->ids['family'].'/photo', 'media/aac/'.$this->ids['card'].'/icon', 'media/game/1/image'] as $path) {
            $this->get("/api/v1/{$path}")->assertForbidden();   // no signature, no file, whoever is signed in
        }
    }

    /** Routes that are meant to be reachable without signing in. Everything else under /api/v1 must refuse a request with no token. */
    private const PUBLIC = ['api/v1/auth/code', 'api/v1/auth/verify', 'api/v1/legal/terms'];

    public function test_every_route_refuses_a_request_with_no_sign_in(): void
    {
        $open = [];
        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/') || in_array($uri, self::PUBLIC, true) || str_starts_with($uri, 'api/v1/media/')) {
                continue;
            }
            $url = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $status = $this->call($method, $url, [], [], [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode();
                $checked++;
                if ($status !== 401) {
                    $open[] = "{$method} {$uri} gave {$status}";
                }
            }
        }

        $this->assertGreaterThan(80, $checked, 'the sweep should cover the whole API');
        $this->assertSame([], $open);
    }

    public function test_every_admin_route_refuses_a_parent(): void
    {
        Sanctum::actingAs($this->victim);
        $open = [];
        $checked = 0;

        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/admin/')) {
                continue;
            }
            $url = '/'.preg_replace('/\{[^}]+\}/', '1', $uri);
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $status = $this->call($method, $url, [], [], [], ['HTTP_ACCEPT' => 'application/json'])->getStatusCode();
                $checked++;
                if ($status !== 403) {
                    $open[] = "{$method} {$uri} gave {$status}";
                }
            }
        }

        $this->assertGreaterThan(20, $checked);
        $this->assertSame([], $open);
    }
}
