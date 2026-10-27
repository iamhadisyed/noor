<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\FcmService;
use App\Services\NotificationPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsAndConsentTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private array $child;
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->parent = User::factory()->create(['subscription_tier' => 'pro']);
        $this->as($this->parent);
        $this->child = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => '2020-01-01'])->json();

        $sent = &$this->sent;
        $this->app->instance(FcmService::class, new class($sent) extends FcmService {
            public function __construct(public array &$sent) {}
            public function sendToUser(int $userId, string $title, string $body, array $data = []): int { $this->sent[] = [$userId, $data['need_type'] ?? null]; return 1; }
        });
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function alert(string $need): void
    {
        $this->as($this->parent)->postJson('/api/v1/child/alert', ['child_id' => $this->child['id'], 'need_type' => $need])->assertOk();
    }

    private function wav(): UploadedFile
    {
        $data = str_repeat("\x00\x00", 2000);
        $hdr = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16) . 'data' . pack('V', strlen($data));
        $path = tempnam(sys_get_temp_dir(), 'snd') . '.wav';
        file_put_contents($path, $hdr . $data);

        return new UploadedFile($path, 'a.wav', 'audio/wav', null, true);
    }

    // ---------- preferences ----------

    public function test_default_preferences_have_no_quiet_hours_and_list_what_can_be_muted(): void
    {
        $r = $this->getJson('/api/v1/me/notifications')->assertOk();
        $r->assertJsonPath('quiet_hours.enabled', false)->assertJsonPath('muted_needs', []);
        $this->assertEqualsCanonicalizing(['potty', 'pain', 'help'], $r->json('urgent'));
        $this->assertEqualsCanonicalizing(['water', 'food', 'sleep', 'hug'], $r->json('mutable'));
    }

    public function test_preferences_are_saved_validated_and_merged(): void
    {
        $this->patchJson('/api/v1/me/notifications', ['quiet_hours' => ['enabled' => true, 'start' => '21:30', 'end' => '06:45'], 'timezone' => 'Asia/Karachi', 'muted_needs' => ['hug']])->assertOk()
            ->assertJsonPath('quiet_hours.start', '21:30')->assertJsonPath('timezone', 'Asia/Karachi');
        $this->patchJson('/api/v1/me/notifications', ['quiet_hours' => ['end' => '07:00']])->assertOk()->assertJsonPath('quiet_hours.start', '21:30')->assertJsonPath('quiet_hours.end', '07:00');

        $this->patchJson('/api/v1/me/notifications', ['quiet_hours' => ['start' => '25:00']])->assertStatus(422);
        $this->patchJson('/api/v1/me/notifications', ['quiet_hours' => ['start' => '9pm']])->assertStatus(422);
        $this->patchJson('/api/v1/me/notifications', ['timezone' => 'Mars/Olympus'])->assertStatus(422);
        $this->patchJson('/api/v1/me/notifications', ['muted_needs' => ['potty']])->assertStatus(422);   // urgent cannot be muted
        $this->patchJson('/api/v1/me/notifications', ['muted_needs' => ['nonsense']])->assertStatus(422);
    }

    // ---------- the policy ----------

    public function test_urgent_alerts_always_buzz_even_in_quiet_hours_and_when_muted(): void
    {
        $this->parent->update(['notification_prefs' => ['quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'], 'muted_needs' => ['water']]]);
        foreach (['potty', 'pain', 'help', 'POTTY'] as $need) {
            $this->assertTrue(NotificationPolicy::shouldPush($this->parent->fresh(), $need, Carbon::parse('2026-01-01 03:00', 'UTC')), $need);
        }
    }

    public function test_quiet_hours_that_cross_midnight_hold_back_the_gentle_alerts(): void
    {
        $this->parent->update(['notification_prefs' => ['timezone' => 'UTC', 'quiet_hours' => ['enabled' => true, 'start' => '22:00', 'end' => '07:00']]]);
        $u = $this->parent->fresh();
        $at = fn ($t) => NotificationPolicy::shouldPush($u, 'water', Carbon::parse("2026-01-01 {$t}", 'UTC'));
        $this->assertFalse($at('22:00'));
        $this->assertFalse($at('23:59'));
        $this->assertFalse($at('02:30'));
        $this->assertFalse($at('06:59'));
        $this->assertTrue($at('07:00'));
        $this->assertTrue($at('12:00'));
        $this->assertTrue($at('21:59'));
    }

    public function test_a_daytime_quiet_window_and_an_empty_one(): void
    {
        $u = $this->parent;
        $u->update(['notification_prefs' => ['timezone' => 'UTC', 'quiet_hours' => ['enabled' => true, 'start' => '13:00', 'end' => '15:00']]]);
        $this->assertFalse(NotificationPolicy::shouldPush($u->fresh(), 'food', Carbon::parse('2026-01-01 14:00', 'UTC')));
        $this->assertTrue(NotificationPolicy::shouldPush($u->fresh(), 'food', Carbon::parse('2026-01-01 15:00', 'UTC')));
        $u->update(['notification_prefs' => ['timezone' => 'UTC', 'quiet_hours' => ['enabled' => true, 'start' => '10:00', 'end' => '10:00']]]);
        $this->assertTrue(NotificationPolicy::shouldPush($u->fresh(), 'food', Carbon::parse('2026-01-01 10:00', 'UTC')));   // start = end means no window
    }

    public function test_the_parents_own_time_zone_decides_what_night_means(): void
    {
        $this->parent->update(['notification_prefs' => ['timezone' => 'Asia/Karachi', 'quiet_hours' => ['enabled' => true, 'start' => '22:00', 'end' => '07:00']]]);
        $u = $this->parent->fresh();
        $this->assertFalse(NotificationPolicy::shouldPush($u, 'water', Carbon::parse('2026-01-01 18:00', 'UTC')));   // 23:00 in Karachi
        $this->assertTrue(NotificationPolicy::shouldPush($u, 'water', Carbon::parse('2026-01-01 10:00', 'UTC')));    // 15:00 in Karachi
    }

    public function test_a_muted_kind_is_held_back_but_the_rest_still_buzz(): void
    {
        $this->parent->update(['notification_prefs' => ['muted_needs' => ['hug']]]);
        $u = $this->parent->fresh();
        $this->assertFalse(NotificationPolicy::shouldPush($u, 'hug'));
        $this->assertTrue(NotificationPolicy::shouldPush($u, 'water'));
        $this->assertTrue(NotificationPolicy::shouldPush($u, 'something unexpected'));
    }

    // ---------- applied to real alerts ----------

    public function test_a_held_back_alert_is_still_logged_and_visible_but_does_not_push(): void
    {
        $this->parent->update(['notification_prefs' => ['muted_needs' => ['hug']]]);
        $this->alert('hug');
        $this->assertSame([], $this->sent);
        $this->assertSame('hug', $this->getJson("/api/v1/children/{$this->child['id']}/needs")->json('0.need_type'));

        $this->alert('potty');
        $this->assertSame([[$this->parent->id, 'potty']], $this->sent);
    }

    public function test_each_parent_in_a_household_has_their_own_settings(): void
    {
        $baba = User::factory()->create(['email' => 'baba@example.com', 'notification_prefs' => ['muted_needs' => ['water']]]);
        HouseholdMember::create(['owner_id' => $this->parent->id, 'email' => $baba->email, 'user_id' => $baba->id, 'status' => 'active']);

        $this->alert('water');
        $this->assertSame([[$this->parent->id, 'water']], $this->sent);        // Baba muted water; the owner did not

        $this->sent = [];
        $this->alert('pain');
        $this->assertEqualsCanonicalizing([$this->parent->id, $baba->id], array_column($this->sent, 0));
    }

    // ---------- consent for family photos and voices ----------

    private function card(array $extra = [])
    {
        return $this->postJson("/api/v1/children/{$this->child['id']}/family", $extra + ['relation_title' => 'Mama', 'display_name' => 'Ammi', 'photo' => UploadedFile::fake()->image('p.jpg', 200, 200), 'audio' => $this->wav()]);
    }

    public function test_a_family_card_needs_the_parents_confirmation_and_records_it(): void
    {
        $this->card()->assertStatus(422)->assertJsonValidationErrors('consent');
        $this->card(['consent' => false])->assertStatus(422);
        $this->assertSame(0, FamilyMember::count());
        $this->assertSame([], Storage::disk('local')->allFiles());              // nothing stored without consent

        $res = $this->card(['consent' => true])->assertCreated();
        $this->assertNotNull($res->json('consented_at'));
        $m = FamilyMember::firstOrFail();
        $this->assertSame($this->parent->id, $m->consented_by);
        $this->assertEqualsWithDelta(now()->timestamp, $m->consented_at->timestamp, 5);
    }

    public function test_the_consent_record_is_listed_and_included_in_the_data_export(): void
    {
        $this->card(['consent' => true])->assertCreated();
        $this->assertNotNull($this->getJson("/api/v1/children/{$this->child['id']}/family")->json('0.consented_at'));

        $res = $this->get('/api/v1/me/export', ['Accept' => 'application/json'])->assertOk();
        $zip = new \ZipArchive();
        $copy = tempnam(sys_get_temp_dir(), 'z');
        copy($res->baseResponse->getFile()->getPathname(), $copy);
        $zip->open($copy);
        $json = json_decode($zip->getFromName('data.json'), true);
        $this->assertNotNull($json['family_cards'][0]['consented_at']);
    }

    public function test_editing_a_card_does_not_need_consent_again(): void
    {
        $id = $this->card(['consent' => true])->json('id');
        $this->patchJson("/api/v1/family/{$id}", ['display_name' => 'Mummy'])->assertOk()->assertJsonPath('display_name', 'Mummy');
    }
}
