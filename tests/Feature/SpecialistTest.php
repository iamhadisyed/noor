<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\ContentFlag;
use App\Models\HouseholdMember;
use App\Models\PracticeSession;
use App\Models\SpecialistAccess;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecialistTest extends TestCase
{
    use RefreshDatabase;

    private User $mama;
    private User $sara;       // the speech therapist
    private array $child;
    private array $pushed = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->mama = User::factory()->create(['email' => 'mama@example.com', 'name' => 'Mama', 'subscription_tier' => 'pro']);
        $this->sara = User::factory()->create(['email' => 'sara@clinic.example', 'name' => 'Sara']);
        $this->as($this->mama);
        $this->child = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => now()->subYears(6)->toDateString(), 'neurotype' => 'speech_delay'])->json();

        $pushed = &$this->pushed;
        $this->app->instance(FcmService::class, new class($pushed) extends FcmService {
            public function __construct(public array &$pushed) {}
            public function sendToUser(int $userId, string $title, string $body, array $data = []): int { $this->pushed[] = [$userId, $title, $body]; return 1; }
        });
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function invite(string $role = 'therapist', string $label = 'Sara, speech therapist'): array
    {
        return $this->as($this->mama)->postJson("/api/v1/children/{$this->child['id']}/specialists", ['role' => $role, 'label' => $label])->assertCreated()->json();
    }

    private function join(?User $who = null): array
    {
        $inv = $this->invite();
        $this->as($who ?? $this->sara)->postJson('/api/v1/specialist/redeem', ['code' => $inv['code']])->assertOk();

        return $inv;
    }

    private function practice(int $total, int $good, int $seconds = 600, ?string $when = null): void
    {
        $s = PracticeSession::create(['child_id' => $this->child['id'], 'language' => 'urdu', 'total_attempts' => $total, 'successful_attempts' => $good, 'duration_seconds' => $seconds]);
        if ($when) { $s->forceFill(['created_at' => $when])->save(); }
    }

    // ---------- the parent creates a code ----------

    public function test_a_code_is_six_digits_shown_once_and_stored_only_as_a_hash(): void
    {
        $inv = $this->invite();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $inv['code']);
        $this->assertSame('pending', $inv['status']);

        $list = $this->getJson("/api/v1/children/{$this->child['id']}/specialists")->assertOk();
        $this->assertArrayNotHasKey('code', $list->json('0'));
        $this->assertStringNotContainsString($inv['code'], $list->getContent());
        $row = SpecialistAccess::firstOrFail();
        $this->assertNotSame($inv['code'], $row->code_hash);
        $this->assertSame(SpecialistAccess::hashCode($inv['code']), $row->code_hash);
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $row->code_expires_at->timestamp, 10);
        $this->assertTrue(AuditLog::where('action', 'specialist.invited')->exists());
    }

    public function test_the_invitation_is_validated_and_limited(): void
    {
        $this->as($this->mama);
        $this->postJson("/api/v1/children/{$this->child['id']}/specialists", ['role' => 'doctor', 'label' => 'x'])->assertStatus(422);
        $this->postJson("/api/v1/children/{$this->child['id']}/specialists", ['role' => 'teacher'])->assertStatus(422);
        foreach (range(1, 5) as $i) { $this->invite('teacher', "Teacher {$i}"); }
        $this->postJson("/api/v1/children/{$this->child['id']}/specialists", ['role' => 'teacher', 'label' => 'One too many'])->assertStatus(422);
    }

    public function test_only_a_parent_of_the_child_can_manage_access(): void
    {
        $this->as(User::factory()->create());
        $this->getJson("/api/v1/children/{$this->child['id']}/specialists")->assertStatus(404);
        $this->postJson("/api/v1/children/{$this->child['id']}/specialists", ['role' => 'teacher', 'label' => 'x'])->assertStatus(404);
        $id = $this->invite()['id'];
        $this->as(User::factory()->create())->deleteJson("/api/v1/specialists/{$id}")->assertStatus(404);
    }

    // ---------- the specialist enters it ----------

    public function test_entering_the_code_gives_read_only_access_to_progress(): void
    {
        $this->practice(10, 8, 600, now()->subDay()->toDateTimeString());
        $this->practice(10, 6, 300);
        $this->postJson("/api/v1/children/{$this->child['id']}/lessons", ['title' => 'Alif to Se'])->assertCreated();
        ChildNeedsLog::create(['child_id' => $this->child['id'], 'need_type' => 'potty']);
        $inv = $this->invite();

        $r = $this->as($this->sara)->postJson('/api/v1/specialist/redeem', ['code' => $inv['code']])->assertOk();
        $r->assertJsonPath('name', 'Ayan')->assertJsonPath('age_years', 6)->assertJsonPath('role', 'therapist');

        $sum = $this->getJson("/api/v1/specialist/children/{$this->child['id']}/summary?days=7")->assertOk();
        $sum->assertJsonPath('practice.sessions', 2)->assertJsonPath('practice.minutes', 15)->assertJsonPath('practice.accuracy_pct', 70)->assertJsonPath('lessons.0.title', 'Alif to Se');
        $this->assertCount(7, $sum->json('daily'));
        // what a specialist must never see
        foreach (['needs', 'ledger', 'family', 'image_url', 'audio_url'] as $secret) {
            $this->assertStringNotContainsString($secret, $sum->getContent(), $secret);
        }
        $this->assertSame('Sara, speech therapist', $this->getJson('/api/v1/specialist/children')->json('0.label'));
        $this->getJson('/api/v1/me')->assertJsonPath('shared_children', 1);
    }

    public function test_a_specialist_cannot_touch_anything_else_about_the_child(): void
    {
        $this->join();
        $id = $this->child['id'];
        $this->as($this->sara);
        $this->getJson('/api/v1/children')->assertExactJson([]);
        foreach (["/api/v1/children/{$id}", "/api/v1/children/{$id}/lessons", "/api/v1/children/{$id}/needs", "/api/v1/children/{$id}/rewards", "/api/v1/children/{$id}/family", "/api/v1/children/{$id}/insights/summary", "/api/v1/children/{$id}/specialists"] as $url) {
            $this->getJson($url)->assertStatus(404);
        }
        $this->patchJson("/api/v1/children/{$id}", ['name' => 'Hacked'])->assertStatus(404);
        $this->deleteJson("/api/v1/children/{$id}")->assertStatus(404);
        $this->postJson("/api/v1/children/{$id}/rewards/bonus", ['minutes' => 10])->assertStatus(404);
        $this->getJson("/api/v1/sync")->assertOk()->assertJsonPath('changes', []);
        $this->assertSame('Ayan', Child::find($id)->name);
    }

    public function test_each_refusal_reads_the_same_and_a_code_works_once(): void
    {
        $inv = $this->invite();
        $expired = $this->invite('teacher', 'Old');
        SpecialistAccess::find($expired['id'])->update(['code_expires_at' => now()->subMinute()]);
        $this->as($this->sara)->postJson('/api/v1/specialist/redeem', ['code' => $inv['code']])->assertOk();

        $other = User::factory()->create();
        $msgs = [];
        foreach (['000000', $inv['code'], $expired['code'], '12', 'abcdef', ''] as $code) {
            $res = $this->as($other)->postJson('/api/v1/specialist/redeem', ['code' => $code]);
            $res->assertStatus(422);
            $msgs[] = $res->json('message');
        }
        $this->assertCount(2, array_unique($msgs));          // the one neutral message, and the validator's for an empty field
        $this->assertSame(0, SpecialistAccess::where('user_id', $other->id)->count());
    }

    public function test_the_code_can_be_typed_with_a_space_in_it(): void
    {
        $inv = $this->invite();
        $this->as($this->sara)->postJson('/api/v1/specialist/redeem', ['code' => substr($inv['code'], 0, 3) . ' ' . substr($inv['code'], 3)])->assertOk();
    }

    public function test_guessing_is_rate_limited(): void
    {
        $real = $this->invite()['code'];
        $this->as($this->sara);
        foreach (range(1, 10) as $i) { $this->postJson('/api/v1/specialist/redeem', ['code' => sprintf('%06d', $i)])->assertStatus(422); }
        $this->postJson('/api/v1/specialist/redeem', ['code' => $real])->assertStatus(429);   // even the right code waits
    }

    public function test_a_parent_or_co_parent_cannot_use_a_code_on_their_own_child(): void
    {
        $inv = $this->invite();
        $this->as($this->mama)->postJson('/api/v1/specialist/redeem', ['code' => $inv['code']])->assertStatus(422);
        $baba = User::factory()->create(['email' => 'baba@example.com']);
        HouseholdMember::create(['owner_id' => $this->mama->id, 'email' => $baba->email, 'user_id' => $baba->id, 'status' => 'active']);
        $this->as($baba)->postJson('/api/v1/specialist/redeem', ['code' => $inv['code']])->assertStatus(422);
        $this->assertSame('pending', SpecialistAccess::find($inv['id'])->status);
    }

    // ---------- ending it ----------

    public function test_revoking_ends_access_at_once_and_cancels_unused_codes(): void
    {
        $inv = $this->join();
        $this->as($this->mama)->deleteJson("/api/v1/specialists/{$inv['id']}")->assertOk();
        $this->as($this->sara)->getJson("/api/v1/specialist/children/{$this->child['id']}/summary")->assertStatus(404);
        $this->getJson('/api/v1/specialist/children')->assertExactJson([]);

        $unused = $this->invite('teacher', 'Never used');
        $this->as($this->mama)->deleteJson("/api/v1/specialists/{$unused['id']}")->assertOk();
        $this->as($this->sara)->postJson('/api/v1/specialist/redeem', ['code' => $unused['code']])->assertStatus(422);
        $this->assertSame([], $this->as($this->mama)->getJson("/api/v1/children/{$this->child['id']}/specialists")->json());
    }

    public function test_a_new_code_replaces_the_old_one(): void
    {
        $first = $this->invite();
        $second = $this->as($this->mama)->postJson("/api/v1/specialists/{$first['id']}/new-code")->assertOk()->json();
        $this->assertNotSame($first['code'], $second['code']);
        $this->as($this->sara)->postJson('/api/v1/specialist/redeem', ['code' => $first['code']])->assertStatus(422);
        $this->postJson('/api/v1/specialist/redeem', ['code' => $second['code']])->assertOk();
        $this->as($this->mama)->postJson("/api/v1/specialists/{$first['id']}/new-code")->assertStatus(422);   // already has access
    }

    public function test_a_specialist_can_leave(): void
    {
        $this->join();
        $this->as($this->sara)->deleteJson("/api/v1/specialist/children/{$this->child['id']}")->assertOk();
        $this->getJson("/api/v1/specialist/children/{$this->child['id']}/summary")->assertStatus(404);
    }

    public function test_the_parent_sees_who_has_access_and_when_they_last_looked(): void
    {
        $this->join();
        $before = $this->as($this->mama)->getJson("/api/v1/children/{$this->child['id']}/specialists")->json('0');
        $this->assertSame('sara@clinic.example', $before['specialist']['email']);
        $this->assertNull($before['last_viewed_at']);

        $this->as($this->sara)->getJson("/api/v1/specialist/children/{$this->child['id']}/summary")->assertOk();
        $this->assertNotNull($this->as($this->mama)->getJson("/api/v1/children/{$this->child['id']}/specialists")->json('0.last_viewed_at'));
    }

    public function test_removing_a_child_or_a_specialists_account_clears_the_access(): void
    {
        $this->join();
        app(\App\Services\AccountEraser::class)->erase($this->sara);
        $this->assertSame(0, SpecialistAccess::count());

        $this->join(User::factory()->create(['email' => 'second@clinic.example']));
        $this->as($this->mama)->deleteJson("/api/v1/children/{$this->child['id']}")->assertOk();
        $this->assertSame(0, SpecialistAccess::count());
    }

    // ---------- exercises ----------

    public function test_a_specialist_sets_an_exercise_and_the_parents_are_told_and_see_it(): void
    {
        $this->join();
        $this->pushed = [];
        $a = $this->as($this->sara)->postJson("/api/v1/specialist/children/{$this->child['id']}/assignments", ['title' => 'Trace Alif slowly', 'note' => 'Five minutes a day, say the sound aloud.', 'due_on' => now()->addWeek()->toDateString()])->assertCreated()->json();

        $this->assertSame([[$this->mama->id, 'New exercise from Sara, speech therapist', 'Trace Alif slowly']], $this->pushed);
        $list = $this->as($this->mama)->getJson("/api/v1/children/{$this->child['id']}/assignments")->assertOk();
        $list->assertJsonPath('0.title', 'Trace Alif slowly')->assertJsonPath('0.status', 'open')->assertJsonPath('0.from.name', 'Sara');

        $this->postJson("/api/v1/assignments/{$a['id']}/done")->assertOk()->assertJsonPath('status', 'done');
        $this->postJson("/api/v1/assignments/{$a['id']}/done", ['done' => false])->assertOk()->assertJsonPath('status', 'open');
    }

    public function test_every_parent_in_the_household_is_told_and_quiet_hours_are_respected(): void
    {
        $baba = User::factory()->create(['email' => 'baba@example.com', 'notification_prefs' => ['timezone' => 'UTC', 'quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']]]);
        HouseholdMember::create(['owner_id' => $this->mama->id, 'email' => $baba->email, 'user_id' => $baba->id, 'status' => 'active']);
        $this->join();
        $this->pushed = [];
        $this->as($this->sara)->postJson("/api/v1/specialist/children/{$this->child['id']}/assignments", ['title' => 'Practise'])->assertCreated();
        $this->assertSame([$this->mama->id], array_column($this->pushed, 0));          // Baba is in quiet hours: it waits in the app
        $this->assertCount(1, $this->as($baba)->getJson("/api/v1/children/{$this->child['id']}/assignments")->json());
    }

    public function test_exercise_rules_and_who_can_change_them(): void
    {
        $this->join();
        $url = "/api/v1/specialist/children/{$this->child['id']}/assignments";
        $this->as($this->sara);
        $this->postJson($url, ['title' => ''])->assertStatus(422);
        $this->postJson($url, ['title' => 'x', 'due_on' => '2020-01-01'])->assertStatus(422);
        $a = $this->postJson($url, ['title' => 'Mine'])->json();
        $this->patchJson("/api/v1/specialist/assignments/{$a['id']}", ['title' => 'Edited'])->assertOk()->assertJsonPath('title', 'Edited');
        $this->assertSame(['Edited'], array_column($this->getJson($url)->json(), 'title'));

        // another specialist, and the parent, cannot edit or delete it through the specialist routes
        $other = User::factory()->create();
        $this->join($other);
        $this->as($other)->patchJson("/api/v1/specialist/assignments/{$a['id']}", ['title' => 'Stolen'])->assertStatus(404);
        $this->deleteJson("/api/v1/specialist/assignments/{$a['id']}")->assertStatus(404);
        $this->as($this->mama)->patchJson("/api/v1/specialist/assignments/{$a['id']}", ['title' => 'Nope'])->assertStatus(404);

        $this->as($this->sara)->deleteJson("/api/v1/specialist/assignments/{$a['id']}")->assertOk();
        $this->assertNull(Assignment::find($a['id']));
    }

    public function test_a_specialist_with_no_access_cannot_set_or_read_exercises(): void
    {
        $url = "/api/v1/specialist/children/{$this->child['id']}/assignments";
        $this->as($this->sara)->postJson($url, ['title' => 'Sneaky'])->assertStatus(404);
        $this->getJson($url)->assertStatus(404);

        $inv = $this->join();
        $a = $this->as($this->sara)->postJson($url, ['title' => 'Before'])->json();
        $this->as($this->mama)->deleteJson("/api/v1/specialists/{$inv['id']}")->assertOk();
        $this->as($this->sara)->postJson($url, ['title' => 'After'])->assertStatus(404);
        $this->patchJson("/api/v1/specialist/assignments/{$a['id']}", ['title' => 'Late edit'])->assertStatus(404);
    }

    public function test_a_parent_of_another_child_cannot_see_these_exercises(): void
    {
        $this->join();
        $this->as($this->sara)->postJson("/api/v1/specialist/children/{$this->child['id']}/assignments", ['title' => 'Private'])->assertCreated();
        $this->as(User::factory()->create())->getJson("/api/v1/children/{$this->child['id']}/assignments")->assertStatus(404);
        $this->postJson('/api/v1/assignments/1/done')->assertStatus(404);
    }

    public function test_exercises_reach_the_phone_through_sync(): void
    {
        $this->join();
        $cursor = $this->as($this->mama)->getJson('/api/v1/sync')->json('cursor');
        $a = $this->as($this->sara)->postJson("/api/v1/specialist/children/{$this->child['id']}/assignments", ['title' => 'Sync me'])->json();

        $changes = $this->as($this->mama)->getJson("/api/v1/sync?cursor={$cursor}")->json('changes');
        $change = collect($changes)->firstWhere('entity', 'assignment');
        $this->assertSame('Sync me', $change['data']['title']);
        $this->assertSame('Sara', $change['data']['from']['name']);
        $this->assertSame('assignment', collect($this->getJson('/api/v1/sync')->json('changes'))->firstWhere('entity', 'assignment')['entity']);   // and in a first download

        $cursor = $this->getJson('/api/v1/sync')->json('cursor');
        $this->as($this->sara)->deleteJson("/api/v1/specialist/assignments/{$a['id']}")->assertOk();
        $this->assertSame('delete', collect($this->as($this->mama)->getJson("/api/v1/sync?cursor={$cursor}")->json('changes'))->firstWhere('entity', 'assignment')['op']);
    }

    // ---------- the review queue covers exercises too ----------

    public function test_a_bad_word_in_an_exercise_goes_to_the_review_queue_and_removing_it_deletes_it(): void
    {
        $this->join();
        $a = $this->as($this->sara)->postJson("/api/v1/specialist/children/{$this->child['id']}/assignments", ['title' => 'Say this stupid word', 'note' => 'fine'])->assertCreated()->json();
        $flag = ContentFlag::where('assignment_id', $a['id'])->firstOrFail();
        $this->assertSame($this->mama->id, $flag->user_id);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->as($admin)->postJson("/api/v1/admin/content/flags/{$flag->id}/resolve", ['action' => 'remove'])->assertOk();
        $this->assertNull(Assignment::find($a['id']));
    }
}
