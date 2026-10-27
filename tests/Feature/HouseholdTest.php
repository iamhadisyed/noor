<?php

namespace Tests\Feature;

use App\Mail\HouseholdInviteMail;
use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HouseholdTest extends TestCase
{
    use RefreshDatabase;

    private User $mama;
    private User $baba;
    private array $child;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->mama = User::factory()->create(['email' => 'mama@example.com', 'name' => 'Mama', 'subscription_tier' => 'pro']);
        $this->baba = User::factory()->create(['email' => 'baba@example.com', 'name' => 'Baba']);   // free plan
        $this->as($this->mama);
        $this->child = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => '2020-01-01'])->json();
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function invite(string $email = 'baba@example.com'): array
    {
        return $this->as($this->mama)->postJson('/api/v1/household/invite', ['email' => $email])->assertCreated()->json();
    }

    private function join(): void
    {
        $inv = $this->invite();
        $this->as($this->baba)->postJson("/api/v1/household/invitations/{$inv['id']}/accept")->assertOk();
    }

    // ---------- inviting ----------

    public function test_an_invitation_is_emailed_and_shows_for_that_address_but_shares_nothing_yet(): void
    {
        Mail::fake();
        $inv = $this->invite();
        Mail::assertSent(HouseholdInviteMail::class, fn ($m) => $m->hasTo('baba@example.com') && $m->inviterName === 'Mama');
        $this->assertSame('invited', $inv['status']);

        $house = $this->as($this->baba)->getJson('/api/v1/household')->assertOk();
        $this->assertSame('mama@example.com', $house->json('invitations.0.from.email'));
        $this->assertSame([], $this->getJson('/api/v1/children')->json());                    // nothing shared until accepted
        $this->getJson("/api/v1/children/{$this->child['id']}")->assertStatus(404);
    }

    public function test_invitations_are_validated_and_limited(): void
    {
        $this->postJson('/api/v1/household/invite', ['email' => 'mama@example.com'])->assertStatus(422);     // yourself
        $this->postJson('/api/v1/household/invite', ['email' => 'not-an-email'])->assertStatus(422);
        $this->invite();
        $this->postJson('/api/v1/household/invite', ['email' => 'BABA@example.com'])->assertStatus(422);       // again, any case
        $this->invite('a@example.com');
        $this->invite('b@example.com');
        $this->postJson('/api/v1/household/invite', ['email' => 'c@example.com'])->assertStatus(422);          // the limit of 3
    }

    public function test_someone_else_cannot_accept_or_decline_an_invitation_meant_for_another_address(): void
    {
        $inv = $this->invite();
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);
        $this->as($stranger)->postJson("/api/v1/household/invitations/{$inv['id']}/accept")->assertStatus(404);
        $this->postJson("/api/v1/household/invitations/{$inv['id']}/decline")->assertStatus(404);
        $this->assertSame('invited', HouseholdMember::find($inv['id'])->status);
    }

    public function test_declining_removes_the_invitation(): void
    {
        $inv = $this->invite();
        $this->as($this->baba)->postJson("/api/v1/household/invitations/{$inv['id']}/decline")->assertOk();
        $this->assertSame(0, HouseholdMember::count());
    }

    // ---------- sharing ----------

    public function test_after_accepting_the_co_parent_works_with_the_children_and_shares_the_plan(): void
    {
        $this->join();

        $this->assertSame('Ayan', $this->getJson('/api/v1/children')->json('0.name'));
        // the free co-parent shares the owner's Pro plan: more than 3 lessons, family cards beyond 2
        foreach (range(1, 5) as $i) {
            $this->postJson("/api/v1/children/{$this->child['id']}/lessons", ['title' => "Week {$i}"])->assertCreated();
        }
        $lesson = $this->getJson("/api/v1/children/{$this->child['id']}/lessons")->json('0');
        $this->patchJson("/api/v1/lessons/{$lesson['id']}", ['title' => 'Renamed by Baba'])->assertOk();

        $this->as($this->mama);
        $this->assertSame(5, count($this->getJson("/api/v1/children/{$this->child['id']}/lessons")->json()));
        $this->assertContains('Renamed by Baba', array_column($this->getJson("/api/v1/children/{$this->child['id']}/lessons")->json(), 'title'));
    }

    public function test_a_co_parent_cannot_delete_the_child_or_manage_the_household_of_someone_else(): void
    {
        $this->join();
        $this->deleteJson("/api/v1/children/{$this->child['id']}")->assertStatus(403);
        $this->assertNotNull(Child::find($this->child['id']));

        $member = HouseholdMember::firstOrFail();
        $this->deleteJson("/api/v1/household/members/{$member->id}")->assertStatus(404);        // not his household
        $this->assertSame('active', $member->fresh()->status);
    }

    public function test_an_unrelated_parent_sees_nothing(): void
    {
        $this->join();
        $other = User::factory()->create(['subscription_tier' => 'pro']);
        $this->as($other)->getJson('/api/v1/children')->assertOk()->assertExactJson([]);
        $this->getJson("/api/v1/children/{$this->child['id']}")->assertStatus(404);
        $this->getJson("/api/v1/children/{$this->child['id']}/needs")->assertStatus(404);
    }

    // ---------- alerts reach both, either can reply ----------

    public function test_an_alert_goes_to_every_parent_and_either_can_reply(): void
    {
        $this->join();
        $sent = [];
        $this->app->instance(FcmService::class, new class($sent) extends FcmService {
            public function __construct(public array &$sent) {}
            public function sendToUser(int $userId, string $title, string $body, array $data = []): int { $this->sent[] = $userId; return 1; }
        });

        $log = $this->as($this->mama)->postJson('/api/v1/child/alert', ['child_id' => $this->child['id'], 'need_type' => 'water'])->assertOk()->json('id');
        $this->assertEqualsCanonicalizing([$this->mama->id, $this->baba->id], $sent);

        $this->as($this->baba)->postJson("/api/v1/needs/{$log}/acknowledge")->assertOk();
        $feed = $this->as($this->mama)->getJson("/api/v1/children/{$this->child['id']}/needs")->json();
        $this->assertSame('Baba', $feed[0]['replied_by']);
        $this->assertSame('on_the_way', $feed[0]['status']);
    }

    // ---------- the phone stays in step for both ----------

    public function test_joining_reloads_the_co_parents_phone_and_changes_reach_both(): void
    {
        $inv = $this->invite();
        $this->as($this->baba);
        $cursor = $this->getJson('/api/v1/sync')->json('cursor');
        $this->postJson("/api/v1/household/invitations/{$inv['id']}/accept")->assertOk();

        $res = $this->getJson("/api/v1/sync?cursor={$cursor}");
        $this->assertTrue($res->json('reset'));
        $this->assertTrue($res->json('snapshot'));
        $this->assertSame('Ayan', collect($res->json('changes'))->firstWhere('entity', 'child')['data']['name']);
        $cursorBaba = $res->json('cursor');

        // Mama edits: Baba's phone gets it
        $this->as($this->mama);
        $cursorMama = $this->getJson('/api/v1/sync')->json('cursor');
        $this->postJson("/api/v1/children/{$this->child['id']}/lessons", ['title' => 'From Mama'])->assertCreated();
        $this->as($this->baba);
        $this->assertSame('From Mama', collect($this->getJson("/api/v1/sync?cursor={$cursorBaba}")->json('changes'))->firstWhere('entity', 'lesson')['data']['title']);

        // Baba edits: Mama's phone gets it
        $cursorBaba = $this->getJson('/api/v1/sync')->json('cursor');
        $this->postJson("/api/v1/children/{$this->child['id']}/lessons", ['title' => 'From Baba'])->assertCreated();
        $this->as($this->mama);
        $titles = collect($this->getJson("/api/v1/sync?cursor={$cursorMama}")->json('changes'))->where('entity', 'lesson')->pluck('data.title')->all();
        $this->assertContains('From Baba', $titles);
        $this->assertNotNull($cursorBaba);
    }

    public function test_removing_a_co_parent_takes_the_children_away_from_their_phone(): void
    {
        $this->join();
        $this->as($this->baba);
        $cursor = $this->getJson('/api/v1/sync')->json('cursor');
        $member = HouseholdMember::firstOrFail();

        $this->as($this->mama)->deleteJson("/api/v1/household/members/{$member->id}")->assertOk();

        $this->as($this->baba);
        $res = $this->getJson("/api/v1/sync?cursor={$cursor}");
        $this->assertTrue($res->json('reset'));
        $this->assertSame([], $res->json('changes'));                                          // a fresh snapshot with nothing in it
        $this->getJson("/api/v1/children/{$this->child['id']}")->assertStatus(404);
        $this->assertTrue(AuditLog::where('action', 'household.removed')->exists());
    }

    public function test_a_co_parent_can_leave(): void
    {
        $this->join();
        $m = HouseholdMember::firstOrFail();
        $this->as($this->baba)->deleteJson("/api/v1/household/joined/{$m->id}")->assertOk();
        $this->getJson('/api/v1/children')->assertExactJson([]);
        $this->as($this->mama)->getJson('/api/v1/household')->assertJsonPath('members', []);
    }

    public function test_deleting_the_owners_account_tells_the_co_parent_and_leaves_nothing_behind(): void
    {
        $this->join();
        $this->as($this->baba);
        $cursor = $this->getJson('/api/v1/sync')->json('cursor');

        app(\App\Services\AccountEraser::class)->erase($this->mama);

        $this->as($this->baba);
        $ops = collect($this->getJson("/api/v1/sync?cursor={$cursor}")->json('changes'))->map(fn ($c) => "{$c['entity']}:{$c['op']}")->all();
        $this->assertContains('child:delete', $ops);
        $this->assertSame(0, HouseholdMember::count());
        $this->getJson('/api/v1/children')->assertExactJson([]);
        $this->assertNotNull(User::find($this->baba->id));                                     // only the owner's account went
    }

    public function test_a_co_parents_own_children_stay_theirs(): void
    {
        $this->join();
        $mine = $this->as($this->baba)->postJson('/api/v1/children', ['name' => 'Zoya', 'date_of_birth' => '2019-01-01'])->assertCreated()->json();
        $this->assertCount(2, $this->getJson('/api/v1/children')->json());                     // Zoya (his) and Ayan (shared)
        $this->as($this->mama);
        $this->assertSame(['Ayan'], array_column($this->getJson('/api/v1/children')->json(), 'name'));   // Mama does not see Zoya
        $this->getJson("/api/v1/children/{$mine['id']}")->assertStatus(404);
    }

    public function test_a_co_parent_can_run_rewards_and_games_for_the_shared_child(): void
    {
        $this->join();
        $this->postJson("/api/v1/children/{$this->child['id']}/rewards/bonus", ['minutes' => 10])->assertOk();
        $start = $this->postJson("/api/v1/children/{$this->child['id']}/games/count/start")->assertCreated();
        $this->postJson("/api/v1/game-sessions/{$start->json('session_id')}/finish", ['rounds_played' => 5, 'correct' => 5, 'seconds_played' => 30])->assertOk();

        // and the other parent sees the same balance
        $this->as($this->mama);
        $this->assertSame(9, $this->getJson("/api/v1/children/{$this->child['id']}/rewards")->json('balance_minutes'));
        $this->as(User::factory()->create());
        $this->postJson("/api/v1/game-sessions/{$start->json('session_id')}/finish", ['rounds_played' => 5, 'correct' => 5, 'seconds_played' => 30])->assertStatus(404);
    }
}
