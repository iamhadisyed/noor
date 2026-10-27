<?php

namespace Tests\Feature;

use App\Jobs\SyncNudgeJob;
use App\Models\ChildNeedsLog;
use App\Models\DeviceToken;
use App\Models\Lesson;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->parent = User::factory()->create(['subscription_tier' => 'pro']);
        $this->as($this->parent);
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function child(string $name = 'Ayan'): array
    {
        return $this->postJson('/api/v1/children', ['name' => $name, 'date_of_birth' => now()->subYears(5)->toDateString()])->assertCreated()->json();
    }

    private function lesson(int $childId, string $title = 'Week 1'): array
    {
        return $this->postJson("/api/v1/children/{$childId}/lessons", ['title' => $title])->assertCreated()->json();
    }

    private function task(int $lessonId, string $title = 'Alif'): array
    {
        return $this->postJson("/api/v1/lessons/{$lessonId}/tasks", ['type' => 'trace_letter', 'title' => $title, 'target' => 'ا'])->assertCreated()->json();
    }

    private function sync(?int $cursor = null)
    {
        return $this->getJson('/api/v1/sync' . ($cursor === null ? '' : "?cursor={$cursor}"))->assertOk();
    }

    // ---------- snapshot ----------

    public function test_first_call_returns_everything_and_a_cursor(): void
    {
        $c = $this->child();
        $l = $this->lesson($c['id']);
        $this->task($l['id']);
        $log = ChildNeedsLog::create(['child_id' => $c['id'], 'need_type' => 'water']);

        $res = $this->sync();
        $res->assertJsonPath('snapshot', true)->assertJsonPath('has_more', false);
        $by = collect($res->json('changes'))->groupBy('entity');
        $this->assertCount(1, $by['child']);
        $this->assertSame('Week 1', $by['lesson'][0]['data']['title']);
        $this->assertCount(1, $by['lesson'][0]['data']['tasks']);
        $this->assertSame($log->id, $by['need'][0]['id']);
        $this->assertGreaterThan(0, $res->json('cursor'));
    }

    public function test_an_empty_account_syncs_cleanly(): void
    {
        $this->sync()->assertJsonPath('changes', [])->assertJsonPath('cursor', 0);
        $this->sync(0)->assertJsonPath('snapshot', false)->assertJsonPath('changes', []);
    }

    // ---------- delta ----------

    public function test_only_what_changed_comes_back_and_repeat_edits_collapse_to_one(): void
    {
        $c = $this->child();
        $a = $this->lesson($c['id'], 'A');
        $b = $this->lesson($c['id'], 'B');
        $cursor = $this->sync()->json('cursor');

        $this->patchJson("/api/v1/lessons/{$a['id']}", ['title' => 'A1'])->assertOk();
        $this->patchJson("/api/v1/lessons/{$a['id']}", ['title' => 'A2'])->assertOk();

        $res = $this->sync($cursor);
        $this->assertCount(1, $res->json('changes'));
        $this->assertSame('A2', $res->json('changes.0.data.title'));
        $this->assertNotSame($b['id'], $res->json('changes.0.id'));

        // nothing new: the same cursor comes back and no changes
        $again = $this->sync($res->json('cursor'));
        $this->assertSame([], $again->json('changes'));
        $this->assertSame($res->json('cursor'), $again->json('cursor'));
    }

    public function test_a_task_change_arrives_as_its_lesson_with_fresh_tasks(): void
    {
        $c = $this->child();
        $l = $this->lesson($c['id']);
        $t = $this->task($l['id'], 'Alif');
        $cursor = $this->sync()->json('cursor');

        $this->patchJson("/api/v1/tasks/{$t['id']}", ['title' => 'Alif (new)'])->assertOk();
        $this->task($l['id'], 'Bay');

        $res = $this->sync($cursor);
        $this->assertCount(1, $res->json('changes'));
        $this->assertSame('lesson', $res->json('changes.0.entity'));
        $this->assertSame(['Alif (new)', 'Bay'], array_column($res->json('changes.0.data.tasks'), 'title'));

        $this->deleteJson("/api/v1/tasks/{$t['id']}")->assertOk();
        $this->assertSame(['Bay'], array_column($this->sync($res->json('cursor'))->json('changes.0.data.tasks'), 'title'));
    }

    public function test_reordering_tasks_is_synced(): void
    {
        $c = $this->child();
        $l = $this->lesson($c['id']);
        $a = $this->task($l['id'], 'One');
        $b = $this->task($l['id'], 'Two');
        $cursor = $this->sync()->json('cursor');

        $this->postJson("/api/v1/lessons/{$l['id']}/tasks/reorder", ['ids' => [$b['id'], $a['id']]])->assertOk();

        $this->assertSame(['Two', 'One'], array_column($this->sync($cursor)->json('changes.0.data.tasks'), 'title'));
    }

    public function test_archiving_is_an_update_and_deleting_a_child_deletes_everything_under_it(): void
    {
        $c = $this->child();
        $l = $this->lesson($c['id']);
        $f = $this->postJson("/api/v1/children/{$c['id']}/family", ['relation_title' => 'Mama', 'display_name' => 'Mama',
            'photo' => \Illuminate\Http\UploadedFile::fake()->image('p.jpg', 100, 100), 'audio' => \Illuminate\Http\UploadedFile::fake()->create('a.webm', 10, 'audio/webm')])->assertCreated();
        $log = ChildNeedsLog::create(['child_id' => $c['id'], 'need_type' => 'water']);
        $cursor = $this->sync()->json('cursor');

        $this->deleteJson("/api/v1/lessons/{$l['id']}")->assertOk();
        $res = $this->sync($cursor);
        $this->assertSame('upsert', $res->json('changes.0.op'));
        $this->assertSame('archived', $res->json('changes.0.data.status'));

        $this->deleteJson("/api/v1/children/{$c['id']}")->assertOk();
        $ops = collect($this->sync($res->json('cursor'))->json('changes'))->map(fn ($x) => "{$x['entity']}:{$x['id']}:{$x['op']}")->all();
        $this->assertContains("child:{$c['id']}:delete", $ops);
        $this->assertContains("lesson:{$l['id']}:delete", $ops);
        $this->assertContains("need:{$log->id}:delete", $ops);
        $this->assertContains("family_member:{$f->json('id')}:delete", $ops);
    }

    public function test_alert_replies_made_on_the_web_reach_the_phone(): void
    {
        $c = $this->child();
        $log = ChildNeedsLog::create(['child_id' => $c['id'], 'need_type' => 'toilet']);
        $cursor = $this->sync()->json('cursor');

        $this->postJson("/api/v1/needs/{$log->id}/acknowledge")->assertOk();

        $res = $this->sync($cursor);
        $this->assertSame('on_the_way', $res->json('changes.0.data.status'));
    }

    // ---------- isolation ----------

    public function test_one_parent_never_sees_another_parents_changes(): void
    {
        $other = User::factory()->create(['subscription_tier' => 'pro']);
        $mine = $this->child();
        $cursor = $this->sync()->json('cursor');

        $this->as($other);
        $theirs = $this->child('Zoya');
        $this->lesson($theirs['id'], 'Secret');
        $this->as($this->parent);

        $res = $this->sync($cursor);
        $this->assertSame([], $res->json('changes'));
        $this->assertStringNotContainsString('Secret', $this->sync()->getContent());
        $this->assertNotNull($mine['id']);
    }

    public function test_platform_templates_are_not_part_of_a_parents_feed(): void
    {
        $this->seed(\Database\Seeders\LessonTemplateSeeder::class);
        $this->assertSame([], $this->sync()->json('changes'));
        $this->assertSame(0, \App\Models\SyncChange::count());
    }

    public function test_a_cursor_older_than_what_is_kept_starts_over(): void
    {
        $this->child();
        Cache::forever('sync:floor', 999999);
        $this->sync(5)->assertJsonPath('reset', true)->assertJsonPath('snapshot', true);
    }

    public function test_sync_needs_a_login(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/v1/sync')->assertStatus(401);
    }

    // ---------- phone and web end up the same ----------

    /** A tiny stand-in for the app's local database: applies changes in order. */
    private function apply(array &$store, array $res): void
    {
        foreach ($res['changes'] as $c) {
            $key = "{$c['entity']}:{$c['id']}";
            $c['op'] === 'delete' ? $store = array_diff_key($store, [$key => 1]) : $store[$key] = $this->stable($c['data']);
        }
        ksort($store);
    }

    /** Links are signed per request, so compare everything else. */
    private function stable(array $data): array
    {
        array_walk_recursive($data, function (&$v, $k) { if (in_array($k, ['image_url', 'audio_url'], true)) { $v = null; } });

        return $data;
    }

    public function test_a_phone_that_syncs_in_steps_ends_up_identical_to_a_fresh_install(): void
    {
        $phone = [];
        $cursor = null;
        $step = function () use (&$phone, &$cursor) {
            do {
                $res = $this->sync($cursor)->json();
                $this->apply($phone, $res);
                $cursor = $res['cursor'];
            } while ($res['has_more']);
        };

        $c = $this->child(); $step();
        $l = $this->lesson($c['id'], 'Week 1'); $t1 = $this->task($l['id'], 'Alif'); $step();
        $t2 = $this->task($l['id'], 'Bay');
        $this->patchJson("/api/v1/tasks/{$t1['id']}", ['title' => 'Alif!', 'needs_audio' => true]);   // "web" edits
        $this->patchJson("/api/v1/children/{$c['id']}", ['track' => 'special']);
        $step();
        $this->deleteJson("/api/v1/tasks/{$t2['id']}");
        $l2 = $this->lesson($c['id'], 'Week 2'); $this->deleteJson("/api/v1/lessons/{$l2['id']}");
        ChildNeedsLog::create(['child_id' => $c['id'], 'need_type' => 'water']);
        $step();

        $fresh = [];
        $this->apply($fresh, $this->sync()->json());
        $this->assertSame($fresh, $phone);
        $this->assertSame('special', $phone["child:{$c['id']}"]['track']);
        $this->assertSame('Alif!', $phone["lesson:{$l['id']}"]['tasks'][0]['title']);
        $this->assertCount(1, $phone["lesson:{$l['id']}"]['tasks']);
    }

    public function test_a_long_backlog_is_delivered_in_pages(): void
    {
        $c = $this->child();
        $cursor = $this->sync()->json('cursor');
        for ($i = 0; $i < 305; $i++) {
            ChildNeedsLog::create(['child_id' => $c['id'], 'need_type' => 'water']);
        }

        $first = $this->sync($cursor);
        $this->assertTrue($first->json('has_more'));
        $this->assertCount(300, $first->json('changes'));
        $second = $this->sync($first->json('cursor'));
        $this->assertFalse($second->json('has_more'));
        $this->assertCount(5, $second->json('changes'));
    }

    // ---------- retries ----------

    public function test_a_retried_write_with_the_same_key_is_done_once(): void
    {
        $c = $this->child();
        $h = ['Idempotency-Key' => 'phone-abc-12345'];

        $first = $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'Once'], $h)->assertCreated();
        $second = $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'Once'], $h)->assertCreated();

        $this->assertSame($first->json('id'), $second->json('id'));
        $second->assertHeader('Idempotent-Replay', 'true');
        $this->assertSame(1, Lesson::where('title', 'Once')->count());
    }

    public function test_without_a_key_two_posts_make_two_lessons_and_a_reused_key_for_another_request_is_refused(): void
    {
        $c = $this->child();
        $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'Twice'])->assertCreated();
        $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'Twice'])->assertCreated();
        $this->assertSame(2, Lesson::where('title', 'Twice')->count());

        $h = ['Idempotency-Key' => 'key-for-one-thing'];
        $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'One'], $h)->assertCreated();
        $this->postJson('/api/v1/children', ['name' => 'Other', 'date_of_birth' => '2020-01-01'], $h)->assertStatus(422);
        $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'x'], ['Idempotency-Key' => 'short'])->assertStatus(422);
    }

    public function test_idempotency_keys_are_private_to_each_parent_and_failures_are_not_remembered(): void
    {
        $c = $this->child();
        $h = ['Idempotency-Key' => 'shared-key-0001'];
        $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => ''], $h)->assertStatus(422);   // not stored
        $this->postJson("/api/v1/children/{$c['id']}/lessons", ['title' => 'Fixed'], $h)->assertCreated();

        $other = User::factory()->create(['subscription_tier' => 'pro']);
        $this->as($other);
        $oc = $this->child('Zoya');
        $this->postJson("/api/v1/children/{$oc['id']}/lessons", ['title' => 'Theirs'], $h)->assertCreated()->assertHeaderMissing('Idempotent-Replay');
    }

    // ---------- nudge ----------

    public function test_many_changes_send_one_nudge_and_the_push_carries_no_content(): void
    {
        \Illuminate\Support\Facades\Bus::fake([SyncNudgeJob::class]);
        Cache::flush();
        $c = $this->child();
        $this->lesson($c['id']);
        $this->lesson($c['id'], 'Two');

        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(SyncNudgeJob::class, 1);

        // the silent message itself: data only, no banner text
        DeviceToken::create(['user_id' => $this->parent->id, 'token' => 'tok', 'device_type' => 'android']);
        $fcm = new class extends FcmService {
            public array $sent = [];
            protected function accessToken(): ?string { return 'x'; }
            protected function credentials(): array { return ['project_id' => 'p']; }
        };
        \Illuminate\Support\Facades\Http::fake(function ($request) use ($fcm) { $fcm->sent[] = $request->data(); return \Illuminate\Support\Facades\Http::response([], 200); });
        $this->assertSame(1, $fcm->sendData($this->parent->id, ['type' => 'sync']));
        $msg = $fcm->sent[0]['message'];
        $this->assertSame(['type' => 'sync'], $msg['data']);
        $this->assertArrayNotHasKey('notification', $msg);
    }
}
