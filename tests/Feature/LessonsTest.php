<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\LessonTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonsTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->parent = User::factory()->create(['subscription_tier' => 'pro']);
        Sanctum::actingAs($this->parent);
        $this->child = $this->parent->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);
    }

    private function lesson(array $extra = [], ?Child $child = null): array
    {
        return $this->postJson('/api/v1/children/' . ($child ?? $this->child)->id . '/lessons', $extra + ['title' => 'Week 1'])->assertCreated()->json();
    }

    private function task(int $lessonId, array $extra = []): array
    {
        return $this->postJson("/api/v1/lessons/{$lessonId}/tasks", $extra + ['type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا'])->assertCreated()->json();
    }

    private function wav(): UploadedFile
    {
        $data = str_repeat("\x00\x00", 2000);
        $hdr = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16) . 'data' . pack('V', strlen($data));
        $path = tempnam(sys_get_temp_dir(), 'snd') . '.wav';
        file_put_contents($path, $hdr . $data);

        return new UploadedFile($path, 'hello.wav', 'audio/wav', null, true);
    }

    public function test_special_child_gets_calmer_defaults_and_standard_child_does_not(): void
    {
        $special = $this->postJson('/api/v1/children', ['name' => 'Zara', 'date_of_birth' => '2021-03-01', 'neurotype' => 'autism_spectrum'])->assertCreated();
        $special->assertJsonPath('track', 'special')->assertJsonPath('support.pace', 'slow')->assertJsonPath('support.no_timers', true)->assertJsonPath('support.max_choices', 3);

        $this->assertSame('standard', $this->getJson("/api/v1/children/{$this->child->id}")->json('track'));
        $this->assertSame('lively', $this->getJson("/api/v1/children/{$this->child->id}")->json('support.praise'));
    }

    public function test_support_profile_is_validated_and_merged(): void
    {
        $this->patchJson("/api/v1/children/{$this->child->id}", ['support_profile' => ['magnet_px' => 99]])->assertStatus(422);
        $this->patchJson("/api/v1/children/{$this->child->id}", ['track' => 'special', 'support_profile' => ['magnet_px' => 38]])->assertOk()->assertJsonPath('support.magnet_px', 38);
        $this->patchJson("/api/v1/children/{$this->child->id}", ['support_profile' => ['max_choices' => 2]])->assertOk()
            ->assertJsonPath('support.magnet_px', 38)->assertJsonPath('support.max_choices', 2);
    }

    public function test_task_types_table_is_served_so_web_and_app_share_the_rules(): void
    {
        $types = collect($this->getJson('/api/v1/task-types')->assertOk()->json())->keyBy('type');

        $this->assertSame('required', $types['listen_repeat']['audio']);
        $this->assertSame('required', $types['match_face']['image']);
        $this->assertSame('optional', $types['trace_letter']['audio']);
    }

    public function test_meta_gives_clients_the_same_defaults_and_limits_as_the_server(): void
    {
        $m = $this->getJson('/api/v1/meta')->assertOk()->json();

        $this->assertSame('slow', $m['support_defaults']['special']['pace']);
        $this->assertSame(600, $m['limits']['image_max_side']);
        $this->assertSame(150, $m['limits']['audio_max_kb']);
        $this->assertSame(1, $m['limits']['children_free']);
        $this->assertCount(6, $m['task_types']);
    }

    public function test_a_draft_can_always_be_saved_but_publish_lists_exactly_what_is_missing(): void
    {
        $lesson = $this->lesson();
        $this->task($lesson['id'], ['type' => 'spell_word', 'title' => 'Cat', 'target' => 'cat']);                          // needs sound
        $this->task($lesson['id'], ['type' => 'match_face', 'title' => 'Mama']);                                              // needs picture and sound
        $this->task($lesson['id'], ['type' => 'trace_letter', 'title' => 'Be', 'target' => 'ب', 'needs_audio' => true]);     // parent says: needs sound
        $this->task($lesson['id'], ['type' => 'trace_letter', 'title' => 'Pe', 'target' => 'پ']);                            // fine

        $res = $this->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertStatus(422);

        $missing = collect($res->json('problems'))->mapWithKeys(fn ($p) => [$p['position'] => $p['missing']])->all();
        $this->assertSame(['sound'], $missing[1]);
        $this->assertSame(['image', 'sound'], $missing[2]);
        $this->assertSame(['sound'], $missing[3]);
        $this->assertArrayNotHasKey(4, $missing);
        $this->assertStringContainsString('needs a sound', $res->json('problems.0.message'));
        $this->assertSame('draft', Lesson::find($lesson['id'])->status);
    }

    public function test_publish_works_once_the_sound_and_picture_are_added(): void
    {
        $lesson = $this->lesson();
        $t = $this->task($lesson['id'], ['type' => 'match_face', 'title' => 'Mama']);

        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('mama.jpg', 900, 700)])->assertOk()->assertJsonPath('has_image', true);
        $this->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertStatus(422)->assertJsonPath('problems.0.missing', ['sound']);

        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'audio', 'file' => $this->wav()])->assertOk()->assertJsonPath('has_sound', true);
        $this->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertOk()->assertJsonPath('status', 'published')->assertJsonPath('ready', true);
    }

    public function test_empty_lesson_cannot_be_published(): void
    {
        $lesson = $this->lesson();

        $this->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertStatus(422)->assertJsonPath('problems.0.missing', ['tasks']);
    }

    public function test_removing_the_sound_from_a_published_lesson_pulls_it_back_to_draft(): void
    {
        $lesson = $this->lesson();
        $t = $this->task($lesson['id'], ['type' => 'listen_repeat', 'title' => 'Say hello', 'target' => 'hello']);
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'audio', 'file' => $this->wav()])->assertOk();
        $this->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertOk();

        $this->deleteJson("/api/v1/tasks/{$t['id']}/media/audio")->assertOk()->assertJsonPath('lesson_unpublished', true);

        $this->assertSame('draft', Lesson::find($lesson['id'])->status);
    }

    public function test_images_are_reencoded_to_a_small_jpeg_and_files_are_private(): void
    {
        $lesson = $this->lesson();
        $t = $this->task($lesson['id']);

        $res = $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('big.png', 2400, 1600)])->assertOk();

        $path = \App\Models\LessonTask::find($t['id'])->image_path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('local')->get($path));
        $this->assertSame(IMAGETYPE_JPEG, $type);
        $this->assertLessThanOrEqual(600, max($w, $h));
        $this->assertStringContainsString('signature=', $res->json('image_url'));
    }

    public function test_media_link_is_signed_and_unsigned_access_is_refused(): void
    {
        $lesson = $this->lesson();
        $t = $this->task($lesson['id']);
        $url = $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('a.jpg', 100, 100)])->json('image_url');

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get("/api/v1/media/{$t['id']}/image")->assertStatus(403);
    }

    public function test_bad_uploads_are_rejected(): void
    {
        $lesson = $this->lesson();
        $t = $this->task($lesson['id']);

        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('x.jpg', 'this is not a picture')])->assertStatus(422);
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'audio', 'file' => UploadedFile::fake()->createWithContent('x.mp3', '<?php echo 1;')])->assertStatus(422);
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'audio', 'file' => UploadedFile::fake()->create('long.wav', 400, 'audio/wav')])->assertStatus(422);   // over 150 KB
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'video', 'file' => $this->wav()])->assertStatus(422);
    }

    public function test_pick_picture_choices_must_fit_the_childs_support_profile(): void
    {
        $this->patchJson("/api/v1/children/{$this->child->id}", ['track' => 'special', 'support_profile' => ['max_choices' => 2]])->assertOk();
        $lesson = $this->lesson();
        $t = $this->task($lesson['id'], ['type' => 'pick_picture', 'title' => 'Which is the cat?', 'options' => ['choices' => ['cat', 'dog', 'cow'], 'correct' => 0]]);
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('cat.jpg', 300, 300)])->assertOk();

        $this->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertStatus(422)->assertJsonPath('problems.0.missing', ['fewer_choices']);

        $this->patchJson("/api/v1/tasks/{$t['id']}", ['options' => ['choices' => ['cat', 'dog'], 'correct' => 0]])->assertOk();
        $this->postJson("/api/v1/lessons/{$lesson['id']}/publish")->assertOk();
    }

    public function test_correct_answer_must_be_one_of_the_choices(): void
    {
        $lesson = $this->lesson();

        $this->postJson("/api/v1/lessons/{$lesson['id']}/tasks", ['type' => 'pick_picture', 'title' => 'q', 'options' => ['choices' => ['a', 'b'], 'correct' => 3]])->assertStatus(422);
    }

    public function test_tasks_can_be_reordered_and_positions_stay_contiguous_after_delete(): void
    {
        $lesson = $this->lesson();
        $a = $this->task($lesson['id'], ['title' => 'A']);
        $b = $this->task($lesson['id'], ['title' => 'B']);
        $c = $this->task($lesson['id'], ['title' => 'C']);

        $this->postJson("/api/v1/lessons/{$lesson['id']}/tasks/reorder", ['ids' => [$c['id'], $a['id'], $b['id']]])->assertOk();
        $this->assertSame(['C', 'A', 'B'], collect($this->getJson("/api/v1/lessons/{$lesson['id']}")->json('tasks'))->pluck('title')->all());

        $this->postJson("/api/v1/lessons/{$lesson['id']}/tasks/reorder", ['ids' => [$a['id']]])->assertStatus(422);

        $this->deleteJson("/api/v1/tasks/{$c['id']}")->assertOk();
        $this->assertSame([1, 2], collect($this->getJson("/api/v1/lessons/{$lesson['id']}")->json('tasks'))->pluck('position')->all());
    }

    public function test_templates_can_be_copied_for_a_child_and_arrive_as_drafts_that_need_media(): void
    {
        $this->seed(LessonTemplateSeeder::class);
        $special = collect($this->getJson('/api/v1/lesson-templates?track=special')->assertOk()->json());
        $this->assertGreaterThanOrEqual(3, $special->count());
        $this->assertTrue($special->every(fn ($t) => $t['track'] === 'special'));

        $routine = $special->firstWhere('title', 'My day: First, Then');
        $copy = $this->postJson("/api/v1/children/{$this->child->id}/lessons/from-template/{$routine['id']}")->assertCreated();

        $copy->assertJsonPath('status', 'draft')->assertJsonPath('track', 'special')->assertJsonPath('ready', false);
        $this->assertCount(3, $copy->json('tasks'));
        $this->assertSame(['image'], $copy->json('problems.0.missing'));
        $this->assertSame(1, Lesson::where('is_template', true)->where('title', 'My day: First, Then')->count());   // the template itself is untouched
    }

    public function test_duplicate_copies_the_lesson_and_its_media_to_another_child(): void
    {
        $other = $this->parent->children()->create(['name' => 'Zara', 'date_of_birth' => '2021-01-01']);
        $lesson = $this->lesson();
        $t = $this->task($lesson['id']);
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('a.jpg', 200, 200)])->assertOk();

        $copy = $this->postJson("/api/v1/lessons/{$lesson['id']}/duplicate", ['child_id' => $other->id])->assertCreated();

        $this->assertSame($other->id, $copy->json('child_id'));
        $this->assertTrue($copy->json('tasks.0.has_image'));
        $this->assertNotSame(\App\Models\LessonTask::find($t['id'])->image_path, \App\Models\LessonTask::find($copy->json('tasks.0.id'))->image_path);
    }

    public function test_free_plan_is_limited_to_three_lessons_per_child(): void
    {
        $this->parent->update(['subscription_tier' => 'free']);
        foreach (range(1, 3) as $i) {
            $this->lesson(['title' => "L{$i}"]);
        }

        $this->postJson("/api/v1/children/{$this->child->id}/lessons", ['title' => 'L4'])->assertStatus(402)->assertJsonPath('upgrade', true);
    }

    public function test_a_parent_cannot_touch_another_parents_lessons_tasks_or_media(): void
    {
        $lesson = $this->lesson();
        $t = $this->task($lesson['id']);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/lessons/{$lesson['id']}")->assertNotFound();
        $this->postJson("/api/v1/lessons/{$lesson['id']}/tasks", ['type' => 'trace_letter', 'title' => 'x', 'target' => 'a'])->assertNotFound();
        $this->patchJson("/api/v1/tasks/{$t['id']}", ['title' => 'hacked'])->assertNotFound();
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        $this->getJson("/api/v1/children/{$this->child->id}/lessons")->assertNotFound();
    }

    public function test_archiving_hides_a_lesson_from_the_list_but_keeps_it(): void
    {
        $lesson = $this->lesson();

        $this->deleteJson("/api/v1/lessons/{$lesson['id']}")->assertOk();

        $this->assertCount(0, $this->getJson("/api/v1/children/{$this->child->id}/lessons")->json());
        $this->assertSame('archived', Lesson::find($lesson['id'])->status);
    }

    public function test_deleting_a_child_removes_every_stored_file(): void
    {
        $lesson = $this->lesson();
        $t = $this->task($lesson['id'], ['type' => 'listen_repeat', 'title' => 'Hi', 'target' => 'hi']);
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'audio', 'file' => $this->wav()])->assertOk();
        $this->postJson("/api/v1/tasks/{$t['id']}/media", ['kind' => 'image', 'file' => UploadedFile::fake()->image('a.jpg', 100, 100)])->assertOk();
        $this->assertNotEmpty(Storage::disk('local')->allFiles('lessons'));

        $this->deleteJson("/api/v1/children/{$this->child->id}")->assertOk();

        $this->assertEmpty(Storage::disk('local')->allFiles('lessons'));
        $this->assertDatabaseMissing('lessons', ['id' => $lesson['id']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'child.deleted']);
    }

    public function test_create_responses_carry_the_same_values_as_a_later_read(): void
    {
        $made = $this->lesson();
        $this->assertSame('draft', $made['status']);
        $this->assertSame(1, $made['week_number']);
        $this->assertFalse($made['is_template']);
        $this->assertEquals($this->getJson("/api/v1/lessons/{$made['id']}")->json('status'), $made['status']);

        $task = $this->task($made['id']);
        $this->assertFalse($task['needs_audio']);
    }

    public function test_one_busy_endpoint_does_not_use_up_another_ones_rate_limit(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/child/alert', ['child_id' => $this->child->id, 'need_type' => 'water'])->assertOk();
        }
        // the data export allows 3 an hour; four alerts must not count against it
        $this->get('/api/v1/me/export')->assertOk();
    }

    public function test_plan_limits_are_listed_for_the_apps(): void
    {
        $this->getJson('/api/v1/billing')->assertJsonPath('limits.free.custom_cards_per_child', 3)->assertJsonPath('limits.pro.custom_cards_per_child', null);
        $this->getJson('/api/v1/meta')->assertJsonPath('limits.custom_cards_per_child_free', 3);
    }
}
