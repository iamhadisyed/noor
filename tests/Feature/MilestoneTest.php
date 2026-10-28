<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\GameProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;
    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = User::factory()->create();
        Sanctum::actingAs($this->parent);
        $this->child = $this->parent->children()->create(['name' => 'Ayan Khan', 'date_of_birth' => now()->subYears(5)->toDateString()]);
    }

    private function practise(int $daysAgo, int $minutes = 10, array $letters = []): void
    {
        $s = $this->child->practiceSessions()->create(['language' => 'urdu', 'total_attempts' => 5, 'successful_attempts' => 4, 'duration_seconds' => $minutes * 60]);
        $s->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
        $s->letters()->createMany(collect($letters)->map(fn ($l) => $l + ['child_id' => $this->child->id, 'language' => 'urdu'])->all());
    }

    private function keys(): array
    {
        return array_column($this->getJson("/api/v1/children/{$this->child->id}/milestones")->assertOk()->json('milestones'), 'key');
    }

    public function test_nothing_is_celebrated_until_it_has_happened(): void
    {
        $this->assertSame([], $this->keys());
        $this->practise(0, 5);
        $this->assertSame([], $this->keys(), 'one short session is not a milestone');
    }

    public function test_streaks_minutes_letters_and_game_levels_are_recognised(): void
    {
        foreach ([4, 3, 2, 1, 0] as $d) {
            $this->practise($d, 15, $d === 0 ? [['letter' => 'ا', 'attempts' => 6, 'successes' => 6]] : []);
        }
        GameProgress::create(['child_id' => $this->child->id, 'game' => 'count', 'level' => 3, 'plays' => 4, 'last_accuracy' => 90]);

        $keys = $this->keys();

        $this->assertContains('streak_3', $keys);
        $this->assertNotContains('streak_7', $keys);
        $this->assertContains('minutes_60', $keys);            // 75 minutes in total
        $this->assertNotContains('minutes_300', $keys);
        $this->assertContains('letters_1', $keys);
        $this->assertContains('game_count_3', $keys);
        $this->assertNotContains('game_count_5', $keys);
    }

    public function test_a_broken_streak_still_counts_when_it_was_reached(): void
    {
        foreach ([20, 19, 18, 10] as $d) {
            $this->practise($d);
        }

        $this->assertContains('streak_3', $this->keys());
    }

    public function test_the_card_is_a_square_png_and_only_for_real_milestones(): void
    {
        foreach ([2, 1, 0] as $d) {
            $this->practise($d);
        }
        $url = "/api/v1/children/{$this->child->id}/milestones/streak_3/card.png";

        $r = $this->get($url)->assertOk();
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        [$w, $h] = getimagesizefromstring($r->getContent());
        $this->assertSame([1080, 1080], [$w, $h]);

        $this->get("/api/v1/children/{$this->child->id}/milestones/streak_30/card.png")->assertNotFound();   // not earned, so it cannot be made
        $this->get("/api/v1/children/{$this->child->id}/milestones/anything/card.png")->assertNotFound();
        $this->getJson($url.'?lang=fr')->assertUnprocessable();
    }

    public function test_name_and_language_change_the_picture_and_the_name_can_be_left_off(): void
    {
        foreach ([2, 1, 0] as $d) {
            $this->practise($d);
        }
        $base = "/api/v1/children/{$this->child->id}/milestones/streak_3/card.png";
        $withName = $this->get($base)->getContent();
        $noName = $this->get($base.'?name=0')->getContent();
        $urdu = $this->get($base.'?lang=ur')->assertOk()->getContent();

        $this->assertNotSame($withName, $noName);
        $this->assertNotSame($withName, $urdu);
        $this->assertSame($noName, $this->get($base.'?name=0')->getContent(), 'same input, same picture');
    }

    public function test_other_families_cannot_see_or_make_cards(): void
    {
        foreach ([2, 1, 0] as $d) {
            $this->practise($d);
        }
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/children/{$this->child->id}/milestones")->assertNotFound();
        $this->get("/api/v1/children/{$this->child->id}/milestones/streak_3/card.png")->assertNotFound();
    }
}
