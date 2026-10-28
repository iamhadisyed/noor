<?php

namespace Tests\Feature;

use App\Models\AacCard;
use App\Models\User;
use App\Services\AccountEraser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $mama;
    private int $kid;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->mama = User::factory()->create(['subscription_tier' => 'pro']);
        Sanctum::actingAs($this->mama);
        $this->kid = $this->mama->children()->create(['name' => 'Ayan', 'date_of_birth' => now()->subYears(5)->toDateString()])->id;
    }

    private function wav(): UploadedFile
    {
        $data = str_repeat("\x00\x00", 2000);
        $hdr = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', strlen($data));
        $path = tempnam(sys_get_temp_dir(), 'snd').'.wav';
        file_put_contents($path, $hdr.$data);

        return new UploadedFile($path, 'toy.wav', 'audio/wav', null, true);
    }

    private function make(array $extra = [], ?int $child = null)
    {
        return $this->post('/api/v1/children/'.($child ?? $this->kid).'/aac-cards', $extra + ['english_label' => 'Blue car', 'category' => 'activities', 'icon' => UploadedFile::fake()->image('car.png', 900, 700)], ['Accept' => 'application/json']);
    }

    public function test_a_parent_makes_a_card_with_a_private_picture_and_voice(): void
    {
        $r = $this->make(['urdu_label' => 'نیلی گاڑی', 'audio' => $this->wav()])->assertCreated()->json();

        $this->assertTrue($r['custom']);
        $this->assertNull($r['icon_path']);
        $this->assertStringContainsString('/media/aac/', $r['icon_url']);
        $this->assertNotNull($r['audio_url']);
        $card = AacCard::find($r['id']);
        $this->assertFalse($card->is_emergency);
        Storage::disk('local')->assertExists($card->icon_path);
        Storage::disk('public')->assertMissing($card->icon_path);
        [$w, $h] = getimagesizefromstring(Storage::disk('local')->get($card->icon_path));
        $this->assertLessThanOrEqual(600, max($w, $h));

        $this->get($r['icon_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/api/v1/media/aac/'.$r['id'].'/icon')->assertForbidden();   // no signed link, no picture
    }

    public function test_the_card_list_shows_stock_and_own_cards_and_never_another_familys(): void
    {
        AacCard::create(['category' => 'needs', 'english_label' => 'Water', 'urdu_label' => 'پانی', 'icon_path' => 'icons/water.png']);
        $mine = $this->make()->json('id');
        $other = User::factory()->create(['subscription_tier' => 'pro']);
        $theirKid = $other->children()->create(['name' => 'Zara', 'date_of_birth' => now()->subYears(4)->toDateString()])->id;
        Sanctum::actingAs($other);
        $theirs = $this->make(['english_label' => 'Secret toy'], $theirKid)->json('id');

        Sanctum::actingAs($this->mama);
        $list = collect($this->getJson('/api/v1/aac-cards')->assertOk()->json());
        $this->assertSame(['Water', 'Blue car'], $list->pluck('english_label')->sortBy(fn ($l) => $l === 'Water' ? 0 : 1)->values()->all());
        $this->assertSame('icons/water.png', $list->firstWhere('english_label', 'Water')['icon_path']);
        $this->assertNotContains($theirs, $list->pluck('id')->all());
        $this->assertContains($mine, $list->pluck('id')->all());

        $this->patchJson("/api/v1/aac-cards/{$theirs}", ['english_label' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/aac-cards/{$theirs}")->assertNotFound();
    }

    public function test_cards_can_be_changed_and_removed_with_their_files(): void
    {
        $r = $this->make()->json();
        $old = AacCard::find($r['id'])->icon_path;

        $this->postJson("/api/v1/aac-cards/{$r['id']}", ['english_label' => 'Red car', 'urdu_label' => '', 'icon' => UploadedFile::fake()->image('new.jpg', 300, 300)])->assertOk()->assertJsonPath('english_label', 'Red car')->assertJsonPath('urdu_label', 'Red car');
        $card = AacCard::find($r['id']);
        $this->assertNotSame($old, $card->icon_path);
        Storage::disk('local')->assertMissing($old);

        $this->post("/api/v1/aac-cards/{$r['id']}", ['icon' => UploadedFile::fake()->createWithContent('bad.png', 'not a picture')], ['Accept' => 'application/json'])->assertUnprocessable();
        Storage::disk('local')->assertExists($card->fresh()->icon_path);   // a bad upload never costs the good picture

        $this->deleteJson("/api/v1/aac-cards/{$r['id']}")->assertOk();
        Storage::disk('local')->assertMissing($card->icon_path);
        $this->assertNull(AacCard::find($r['id']));
    }

    public function test_checks_on_what_can_be_made(): void
    {
        $this->make(['category' => 'emergency'])->assertUnprocessable();                       // never an emergency card
        $this->make(['icon' => UploadedFile::fake()->createWithContent('x.png', 'nope')])->assertUnprocessable();
        $this->make(['english_label' => 'stupid'])->assertUnprocessable()->assertJsonPath('message', 'Please choose a different word for this card.');
        $this->make(['audio' => UploadedFile::fake()->createWithContent('x.mp3', 'nope')])->assertUnprocessable();
        $this->assertSame(0, AacCard::count(), 'a refused card leaves nothing behind');
    }

    public function test_the_free_plan_has_a_limit_and_pro_does_not(): void
    {
        $this->mama->update(['subscription_tier' => 'free']);
        foreach (range(1, 3) as $i) {
            $this->make(['english_label' => "Card {$i}"])->assertCreated();
        }
        $this->make(['english_label' => 'Card 4'])->assertStatus(402)->assertJsonPath('upgrade', true);

        $this->mama->update(['subscription_tier' => 'pro']);
        $this->make(['english_label' => 'Card 4'])->assertCreated();
    }

    public function test_a_custom_card_can_be_used_for_an_alert_only_by_its_own_child(): void
    {
        $id = $this->make()->json('id');
        $other = $this->mama->children()->create(['name' => 'Sana', 'date_of_birth' => now()->subYears(3)->toDateString()])->id;

        $this->postJson('/api/v1/child/alert', ['child_id' => $this->kid, 'need_type' => 'Toy', 'card_id' => $id])->assertSuccessful();
        $this->postJson('/api/v1/child/alert', ['child_id' => $other, 'need_type' => 'Toy', 'card_id' => $id])->assertUnprocessable();
    }

    public function test_cards_sync_to_the_app_and_household_and_clean_up_on_erase(): void
    {
        $id = $this->make()->json('id');

        $r = $this->getJson('/api/v1/sync')->assertOk()->json();
        $card = collect($r['changes'])->firstWhere('entity', 'aac_card');
        $this->assertSame($id, $card['id']);
        $this->assertNotNull($card['data']['icon_url']);
        $cursor = $r['cursor'];

        $this->deleteJson("/api/v1/aac-cards/{$id}")->assertOk();
        $ops = collect($this->getJson('/api/v1/sync?cursor='.$cursor)->json('changes'))->map(fn ($c) => "{$c['entity']}:{$c['id']}:{$c['op']}")->all();
        $this->assertContains("aac_card:{$id}:delete", $ops);

        $again = $this->make()->json('id');
        $path = AacCard::find($again)->icon_path;
        app(AccountEraser::class)->erase($this->mama);
        Storage::disk('local')->assertMissing($path);
    }
}
