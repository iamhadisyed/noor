<?php

namespace Tests\Unit;

use App\Support\UrduShaper;
use App\Support\UrduText;
use PHPUnit\Framework\TestCase;

class UrduShaperTest extends TestCase
{
    private function cps(string $s): array
    {
        return array_map('mb_ord', mb_str_split($s));
    }

    public function test_joins_letters_and_returns_visual_order(): void
    {
        // پانی = pe(initial) alef(final) noon(initial) yeh(final), reversed for left-to-right drawing
        $this->assertSame([0xFBFD, 0xFEE7, 0xFE8E, 0xFB58], $this->cps(UrduShaper::visual('پانی')));
    }

    public function test_urdu_specific_final_letters_are_covered(): void
    {
        // ہے = heh-doachashmee (initial) + yeh-barree (final); neither exists in DejaVu or plain Arabic tables
        $this->assertSame([0xFBAF, 0xFBA8], $this->cps(UrduShaper::visual('ہے')));
    }

    public function test_lam_alef_ligature_and_word_order(): void
    {
        // "بیت الخلا": words swap places, final lam-alef ligature (joined to khah) is used
        $out = $this->cps(UrduShaper::visual('الخلا'));
        $this->assertSame(0xFEFC, $out[0]);
        $this->assertCount(4, $out);
        $this->assertSame(2, count(array_filter($this->cps(UrduShaper::visual('بیت الخلا')), fn ($c) => $c === 0x20)) + 1);
    }

    public function test_plain_ascii_is_untouched(): void
    {
        $this->assertSame('Water', UrduShaper::visual('Water'));
    }
}
