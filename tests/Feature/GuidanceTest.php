<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_autism_guide_is_pro_only_and_the_free_plan_sees_what_it_would_get(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $r = $this->getJson('/api/v1/guidance/autism')->assertStatus(402);
        $r->assertJsonPath('upgrade', true);
        $this->assertCount(5, $r->json('preview'));
        $this->assertArrayNotHasKey('sections', $r->json(), 'no guide content is given away');
        $this->assertArrayNotHasKey('disclaimer', $r->json());
    }

    public function test_a_pro_parent_gets_the_whole_guide_with_the_disclaimer_in_both_languages(): void
    {
        Sanctum::actingAs(User::factory()->create(['subscription_tier' => 'pro']));
        $g = $this->getJson('/api/v1/guidance/autism')->assertOk()->json();

        foreach (['en', 'ur'] as $l) {
            $this->assertStringContainsString($l === 'en' ? 'not medical advice' : 'طبی مشورہ نہیں', $g['disclaimer'][$l]);
            $this->assertNotEmpty($g['closing'][$l]);
            $this->assertNotEmpty($g['title'][$l]);
        }
        $this->assertSame(['tests', 'food', 'exercise', 'daily', 'doctor'], array_column($g['sections'], 'key'));
        foreach ($g['sections'] as $s) {
            $this->assertNotEmpty($s['intro']['en']);
            $this->assertNotEmpty($s['intro']['ur']);
            $this->assertGreaterThanOrEqual(5, count($s['items']), $s['key']);
            foreach ($s['items'] as $item) {
                foreach (['title', 'text'] as $f) {
                    foreach (['en', 'ur'] as $l) {
                        $this->assertNotSame('', trim($item[$f][$l]), "{$s['key']} {$f} {$l}");
                    }
                }
            }
        }
    }

    public function test_the_guide_never_promises_a_cure_and_says_no_blood_test_diagnoses_autism(): void
    {
        Sanctum::actingAs(User::factory()->create(['subscription_tier' => 'pro']));
        $g = $this->getJson('/api/v1/guidance/autism')->json();
        $text = strtolower(json_encode($g['sections'], JSON_UNESCAPED_UNICODE));
        $tests = collect($g['sections'])->firstWhere('key', 'tests');
        $food = collect($g['sections'])->firstWhere('key', 'food');

        $this->assertStringContainsString('no blood test that can diagnose autism', strtolower($tests['intro']['en']));
        $this->assertStringContainsString('no food or diet treats or cures autism', strtolower($food['intro']['en']));
        foreach (['cure your child', 'will cure', 'guaranteed', 'miracle', 'chelation', 'bleach'] as $bad) {
            $this->assertStringNotContainsString($bad, $text);
        }
        // the testing section names the usual general checks
        $names = strtolower(json_encode($tests['items']));
        foreach (['blood count', 'iron', 'vitamin d', 'b12', 'thyroid', 'hearing'] as $want) {
            $this->assertStringContainsString($want, $names);
        }
        // a doctor is mentioned in every section's items or intro, so no part reads as a self-treatment plan
        foreach ($g['sections'] as $s) {
            $this->assertMatchesRegularExpression('/doctor|therapist|dietitian|hospital/i', json_encode($s['items']) . json_encode($s['intro']), $s['key']);
        }
    }

    public function test_an_unknown_guide_is_not_found_and_the_list_names_the_guides(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/guidance/nonsense')->assertNotFound();
        $this->getJson('/api/v1/guidance')->assertOk()->assertJsonPath('guides.0.guide', 'autism');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/guidance/autism')->assertUnauthorized();
    }
}
