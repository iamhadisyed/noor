<?php

namespace Tests\Feature;

use App\Models\ContentFlag;
use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LessonImportTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cache::flush();
        config(['kindo.groq_keys' => ['g1'], 'kindo.gemini_keys' => ['m1']]);
        $this->parent = User::factory()->create(['subscription_tier' => 'pro']);
        Sanctum::actingAs($this->parent);
        $this->child = $this->parent->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('page.jpg', 1200, 900);
    }

    private function model(array $outline): array
    {
        return ['choices' => [['message' => ['content' => json_encode($outline, JSON_UNESCAPED_UNICODE)]]]];
    }

    private function import(array $extra = [])
    {
        return $this->post("/api/v1/children/{$this->child->id}/lessons/import", $extra + ['photo' => $this->photo()], ['Accept' => 'application/json']);
    }

    public function test_a_photo_becomes_a_draft_lesson_and_is_never_stored(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->model([
            'title' => 'Week 3: Bay and Pay',
            'tasks' => [
                ['type' => 'trace_letter', 'title' => 'Trace Bay', 'instruction' => 'Slowly.', 'target' => 'ب'],
                ['type' => 'spell_word', 'title' => '', 'target' => 'باپ'],
                ['type' => 'pick_picture', 'title' => 'Needs a picture', 'target' => 'x'],      // a type a photo cannot create
                ['type' => 'trace_letter', 'title' => 'Again', 'target' => 'ب'],                  // duplicate
                ['type' => 'listen_repeat', 'title' => 'Say it', 'target' => '   '],             // empty target
            ],
        ]))]);

        $r = $this->import(['language' => 'urdu', 'week_number' => 3])->assertCreated()->json();

        $this->assertSame('Week 3: Bay and Pay', $r['title']);
        $this->assertSame('draft', $r['status']);
        $this->assertSame('urdu', $r['language']);
        $this->assertSame(3, $r['week_number']);
        $this->assertSame(['trace_letter', 'spell_word'], array_column($r['tasks'], 'type'));
        $this->assertSame('باپ', $r['tasks'][1]['target']);
        $this->assertSame('باپ', $r['tasks'][1]['title'], 'a missing title falls back to the target');
        $this->assertSame([1, 2], array_column($r['tasks'], 'position'));
        $this->assertFalse($r['ready'], 'spell_word still needs a recorded sound before it can be published');
        $this->assertSame([], Storage::disk('local')->allFiles(), 'the photo is not kept');
        Http::assertSent(fn ($req) => str_contains($req->url(), 'groq') && str_contains(json_encode($req->data(), JSON_UNESCAPED_SLASHES), 'data:image/jpeg;base64,'));
    }

    public function test_it_fails_over_to_gemini_with_the_photo_inline(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([], 429),
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['title' => 'Alphabet', 'tasks' => [['type' => 'trace_letter', 'title' => 'Alif', 'target' => 'ا']]], JSON_UNESCAPED_UNICODE)]]]]]]),
        ]);

        $this->import()->assertCreated()->assertJsonPath('tasks.0.target', 'ا');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage') && $r->hasHeader('x-goog-api-key', 'm1')
            && ($r->data()['contents'][0]['parts'][1]['inline_data']['mime_type'] ?? null) === 'image/jpeg');
    }

    public function test_unreadable_pages_and_provider_outages_give_a_plain_message_and_create_nothing(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->model(['title' => '', 'tasks' => []])), 'generativelanguage.googleapis.com/*' => Http::response([], 500)]);
        $this->import()->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'clearer'));

        Http::fake(['*' => Http::response([], 500)]);
        $this->import()->assertUnprocessable();

        $this->assertSame(0, $this->child->lessons()->count());
    }

    public function test_it_needs_pro_a_real_picture_and_your_own_child(): void
    {
        Http::fake();
        $this->parent->update(['subscription_tier' => 'free']);
        $this->import()->assertStatus(402)->assertJsonPath('upgrade', true);
        Http::assertNothingSent();

        $this->parent->update(['subscription_tier' => 'pro']);
        $this->import(['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertUnprocessable();
        $this->import(['photo' => UploadedFile::fake()->createWithContent('fake.jpg', 'not a picture')])->assertUnprocessable();

        Sanctum::actingAs(User::factory()->create(['subscription_tier' => 'pro']));
        $this->import()->assertNotFound();
    }

    public function test_bad_words_in_an_imported_lesson_are_flagged_like_any_other(): void
    {
        $bad = collect(config('moderation.terms', []))->first() ?? 'fuck';
        Http::fake(['api.groq.com/*' => Http::response($this->model(['title' => 'Page', 'tasks' => [['type' => 'spell_word', 'title' => $bad, 'target' => $bad]]]))]);

        $this->import()->assertCreated();

        $this->assertGreaterThan(0, ContentFlag::count());
    }

    public function test_markup_from_the_model_is_stripped(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->model(['title' => '<script>x</script>Letters', 'tasks' => [['type' => 'trace_letter', 'title' => '<b>Alif</b>', 'target' => 'ا']]]))]);

        $r = $this->import()->assertCreated()->json();

        $this->assertStringNotContainsString('<', $r['title'].$r['tasks'][0]['title']);
    }
}
