<?php

namespace App\Services;

use App\Support\UrduText;

/**
 * A square picture a parent can share, for example on WhatsApp, when their child reaches a milestone.
 * It carries only what the parent chooses: the first name (optional), the milestone and the Kindo name.
 * No photo, age, diagnosis or other detail ever goes on it.
 */
class BragCard
{
    public const SIZE = 1080;

    private const PRAISE = ['en' => 'Keep going!', 'ur' => 'شاباش!'];
    private const MADE_WITH = ['en' => 'Made with Kindo', 'ur' => 'Kindo کے ساتھ'];

    /** @return string PNG bytes */
    public function render(array $milestone, ?string $name, string $lang = 'en'): string
    {
        $lang = $lang === 'ur' ? 'ur' : 'en';
        $font = resource_path('fonts/'.UrduText::FONT_BOLD);
        $S = self::SIZE;
        $im = imagecreatetruecolor($S, $S);
        imagealphablending($im, true);

        $c = fn (string $hex) => imagecolorallocate($im, ...sscanf(ltrim($hex, '#'), '%02x%02x%02x'));
        [$lavender, $white, $butter, $ink, $gray, $line] = [$c('#cabaef'), $c('#ffffff'), $c('#fff0ad'), $c('#120c15'), $c('#6f6a78'), $c('#e7e3ef')];

        imagefilledrectangle($im, 0, 0, $S, $S, $lavender);
        $this->roundRect($im, 56, 56, $S - 56, $S - 56, 72, $white);

        // The star, with the number inside.
        $this->star($im, $S / 2, 340, 250, $butter);
        $num = (string) $milestone['value'];
        $this->centred($im, $num, $font, strlen($num) > 2 ? 130 : 180, 345, $ink, 460, vMid: true);

        $y = 690;
        $name = trim((string) $name);
        if ($name !== '') {
            $this->centred($im, UrduText::shape(mb_substr($name, 0, 24)), $font, 72, $y, $ink, 840);
            $y += 84;
        }
        $this->centred($im, UrduText::shape($milestone['title'][$lang]), $font, 52, $y, $ink, 880);
        $y += 74;
        $this->centred($im, UrduText::shape(self::PRAISE[$lang]), $font, 44, $y, $gray, 840);

        imageline($im, 200, $S - 160, $S - 200, $S - 160, $line);
        $made = UrduText::shape(self::MADE_WITH[$lang]);
        $this->centred($im, $made, $font, 34, $S - 105, $gray, 700);
        $this->brandMark($im, $font, $made, 34, $S - 105);

        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** Draw text centred on x, with its baseline at $y (or its middle at $y), shrinking it until it fits $maxWidth. */
    private function centred($im, string $text, string $font, int $px, int $y, int $color, int $maxWidth, bool $vMid = false): void
    {
        $box = imagettfbbox($px, 0, $font, $text);
        while (($box[2] - $box[0]) > $maxWidth && $px > 24) {
            $px -= 2;
            $box = imagettfbbox($px, 0, $font, $text);
        }
        $w = $box[2] - $box[0];
        $x = (int) ((self::SIZE - $w) / 2 - $box[0]);
        $base = $vMid ? (int) ($y - ($box[1] + $box[7]) / 2) : $y;
        imagettftext($im, $px, 0, $x, $base, $color, $font, $text);
    }

    /** The Kindo mark, just left of the "Made with Kindo" line. */
    private function brandMark($im, string $font, string $text, int $px, int $baseline): void
    {
        $file = public_path('brand/mark-96.png');
        if (! is_file($file) || ! ($mark = @imagecreatefrompng($file))) {
            return;
        }
        $box = imagettfbbox($px, 0, $font, $text);
        $w = $box[2] - $box[0];
        $size = 64;
        $x = (int) (self::SIZE / 2 - $w / 2 - $size - 20);
        $y = $baseline - 44;
        imagealphablending($im, true);
        imagecopyresampled($im, $mark, max(60, $x), $y, 0, 0, $size, $size, imagesx($mark), imagesy($mark));
    }

    private function star($im, float $cx, float $cy, float $r, int $color): void
    {
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $rad = $i % 2 === 0 ? $r : $r * 0.62;
            $a = -M_PI / 2 + $i * M_PI / 5;
            array_push($pts, (int) round($cx + $rad * cos($a)), (int) round($cy + $rad * sin($a)));
        }
        imagefilledpolygon($im, $pts, $color);
    }

    private function roundRect($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }
}
