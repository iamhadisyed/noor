<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FamilyMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private array $child;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->parent = User::factory()->create(['subscription_tier' => 'pro']);
        $this->as($this->parent);
        $this->child = $this->postJson('/api/v1/children', ['name' => 'Ayan', 'date_of_birth' => '2020-01-01'])->json();
    }

    private function as(User $u): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($u);

        return $this;
    }

    private function wav(): UploadedFile
    {
        $data = str_repeat("\x00\x00", 2000);
        $hdr = 'RIFF' . pack('V', 36 + strlen($data)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16) . 'data' . pack('V', strlen($data));
        $path = tempnam(sys_get_temp_dir(), 'snd') . '.wav';
        file_put_contents($path, $hdr . $data);

        return new UploadedFile($path, 'hello.wav', 'audio/wav', null, true);
    }

    private function card(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/children/{$this->child['id']}/family", $extra + [
            'relation_title' => 'Mama', 'display_name' => 'Ammi', 'photo' => UploadedFile::fake()->image('p.jpg', 1200, 900), 'audio' => $this->wav(),
        ]);
    }

    public function test_a_new_card_is_stored_privately_resized_and_served_by_signed_link(): void
    {
        $res = $this->card()->assertCreated();

        $m = FamilyMember::findOrFail($res->json('id'));
        Storage::disk('local')->assertExists($m->photo_path);
        Storage::disk('local')->assertExists($m->audio_prompt_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($m->photo_path));
        $this->assertSame(600, max($w, $h));                                   // re-encoded, not the 1200px original

        $res->assertJsonMissingPath('photo_path')->assertJsonMissingPath('audio_prompt_path');
        $photo = $res->json('photo_url');
        $this->assertStringContainsString('signature=', $photo);
        $this->get($photo)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get($res->json('audio_url'))->assertOk();
        $this->get("/api/v1/media/family/{$m->id}/photo")->assertStatus(403);   // no signature, no picture
        $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $photo))->assertStatus(403);
    }

    public function test_index_never_leaks_storage_paths(): void
    {
        $this->card()->assertCreated();
        $body = $this->getJson("/api/v1/children/{$this->child['id']}/family")->assertOk()->getContent();
        $m = FamilyMember::firstOrFail();
        $this->assertStringNotContainsString(basename($m->photo_path), $body);
        $this->assertStringNotContainsString(basename($m->audio_prompt_path), $body);
        $this->assertStringNotContainsString('photo_path', $body);
    }

    public function test_files_are_checked_by_content_and_nothing_is_left_behind_on_failure(): void
    {
        $script = UploadedFile::fake()->createWithContent('voice.mp3', "<?php echo 'x';");
        $this->card(['audio' => $script])->assertStatus(422);
        $this->card(['photo' => UploadedFile::fake()->createWithContent('p.jpg', 'not a picture')])->assertStatus(422);
        $this->assertSame(0, FamilyMember::count());
        $this->assertSame([], Storage::disk('local')->allFiles());              // the good photo was cleaned up too
    }

    public function test_free_plan_still_limits_cards(): void
    {
        $free = User::factory()->create();
        $this->as($free);
        $c = $this->postJson('/api/v1/children', ['name' => 'Zoya', 'date_of_birth' => '2020-01-01'])->json();
        foreach ([1, 2] as $i) {
            $this->postJson("/api/v1/children/{$c['id']}/family", ['relation_title' => 'x', 'display_name' => "n{$i}", 'photo' => UploadedFile::fake()->image('p.jpg'), 'audio' => $this->wav()])->assertCreated();
        }
        $this->postJson("/api/v1/children/{$c['id']}/family", ['relation_title' => 'x', 'display_name' => 'n3', 'photo' => UploadedFile::fake()->image('p.jpg'), 'audio' => $this->wav()])->assertStatus(402);
    }

    public function test_a_parent_can_rename_and_swap_files_and_old_ones_are_removed(): void
    {
        $id = $this->card()->json('id');
        $old = FamilyMember::find($id);
        [$oldPhoto, $oldAudio] = [$old->photo_path, $old->audio_prompt_path];

        $this->postJson("/api/v1/family/{$id}", ['display_name' => 'Mummy', 'photo' => UploadedFile::fake()->image('n.jpg', 300, 300)])->assertOk()->assertJsonPath('display_name', 'Mummy');
        $new = FamilyMember::find($id);
        $this->assertNotSame($oldPhoto, $new->photo_path);
        Storage::disk('local')->assertMissing($oldPhoto);
        Storage::disk('local')->assertExists($oldAudio);                         // untouched
        $this->patchJson("/api/v1/family/{$id}", ['relation_title' => 'Mother'])->assertOk()->assertJsonPath('relation_title', 'Mother');
    }

    public function test_a_bad_replacement_keeps_the_good_original(): void
    {
        $id = $this->card()->json('id');
        $before = FamilyMember::find($id)->photo_path;

        $this->postJson("/api/v1/family/{$id}", ['photo' => UploadedFile::fake()->image('n.jpg'), 'audio' => UploadedFile::fake()->createWithContent('v.mp3', 'garbage')])->assertStatus(422);

        $this->assertSame($before, FamilyMember::find($id)->photo_path);
        Storage::disk('local')->assertExists($before);
    }

    public function test_delete_removes_the_files_and_it_syncs(): void
    {
        $id = $this->card()->json('id');
        $m = FamilyMember::find($id);
        $cursor = $this->getJson('/api/v1/sync')->json('cursor');

        $this->deleteJson("/api/v1/family/{$id}")->assertOk();

        $this->assertSame([], Storage::disk('local')->allFiles());
        $ops = $this->getJson("/api/v1/sync?cursor={$cursor}")->json('changes');
        $this->assertSame(['entity' => 'family_member', 'id' => $id, 'op' => 'delete'], $ops[0]);
        $this->assertNotNull($m);
    }

    public function test_new_and_edited_cards_reach_the_phone_with_working_links(): void
    {
        $cursor = $this->getJson('/api/v1/sync')->json('cursor');
        $id = $this->card()->json('id');

        $res = $this->getJson("/api/v1/sync?cursor={$cursor}");
        $change = collect($res->json('changes'))->firstWhere('entity', 'family_member');
        $this->assertSame($id, $change['id']);
        $this->assertSame('Ammi', $change['data']['display_name']);
        $this->get($change['data']['photo_url'])->assertOk();
        $this->assertArrayNotHasKey('photo_path', $change['data']);
    }

    public function test_another_parent_cannot_change_or_delete_a_card(): void
    {
        $id = $this->card()->json('id');
        $this->as(User::factory()->create(['subscription_tier' => 'pro']));
        $this->patchJson("/api/v1/family/{$id}", ['display_name' => 'Hacked'])->assertStatus(404);
        $this->deleteJson("/api/v1/family/{$id}")->assertStatus(404);
        $this->assertSame('Ammi', FamilyMember::find($id)->display_name);
    }

    public function test_deleting_a_child_removes_the_family_files(): void
    {
        $this->card()->assertCreated();
        $this->deleteJson("/api/v1/children/{$this->child['id']}")->assertOk();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_admin_can_open_a_flagged_family_card_and_it_is_audited(): void
    {
        $id = $this->card(['display_name' => 'Stupid head'])->assertCreated()->json('id');
        $flag = \App\Models\ContentFlag::where('family_member_id', $id)->firstOrFail();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->as($this->parent)->getJson("/api/v1/admin/content/family/{$id}")->assertStatus(403);
        $res = $this->as($admin)->getJson("/api/v1/admin/content/family/{$id}")->assertOk();
        $res->assertJsonPath('child_name', 'Ayan')->assertJsonPath('owner.email', $this->parent->email);
        $this->get($res->json('photo_url'))->assertOk();
        $this->assertTrue(AuditLog::where('action', 'admin.content_viewed')->where('subject_id', $id)->exists());

        $this->as($admin)->postJson("/api/v1/admin/content/flags/{$flag->id}/resolve", ['action' => 'remove'])->assertOk();
        $this->assertNull(FamilyMember::find($id));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_the_move_command_brings_old_public_files_into_private_storage_and_is_safe_to_repeat(): void
    {
        $jpeg = (function () { ob_start(); imagejpeg(imagecreatetruecolor(1500, 1000)); return ob_get_clean(); })();
        Storage::disk('public')->put('family/1/old.jpg', $jpeg);
        Storage::disk('public')->put('family/1/old.wav', file_get_contents($this->wav()->getRealPath()));
        $m = new FamilyMember(['child_id' => $this->child['id'], 'relation_title' => 'Baba', 'display_name' => 'Baba', 'photo_path' => 'family/1/old.jpg', 'audio_prompt_path' => 'family/1/old.wav']);
        $m->save();

        $this->artisan('kindo:privatize-media')->expectsOutputToContain('Moved 2')->assertSuccessful();

        $this->assertSame([], Storage::disk('public')->allFiles());
        Storage::disk('local')->assertExists('family/1/old.jpg');
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get('family/1/old.jpg'));
        $this->assertSame(600, max($w, $h));
        $this->assertNotNull($m->fresh()->audio_mime);
        $this->get($this->getJson("/api/v1/children/{$this->child['id']}/family")->json('0.photo_url'))->assertOk();

        $this->artisan('kindo:privatize-media')->expectsOutputToContain('Moved 0')->assertSuccessful();
    }
}
