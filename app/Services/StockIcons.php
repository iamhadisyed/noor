<?php

namespace App\Services;

/**
 * Plain, original placeholder pictures for the stock picture cards, drawn with GD so the printable PECS sheet and the apps
 * have something to show before a designer supplies final artwork. Replace any file in public/aac with real art and it is used as is.
 */
class StockIcons
{
    public const SIZE = 512;
    private const SCALE = 2;   // drawn at 2x and shrunk, which smooths the edges

    /** @var array<string, string> card name => the method that draws it */
    public const CARDS = [
        'water' => 'water', 'potty' => 'potty', 'milk' => 'milk', 'bed' => 'bed', 'food' => 'food', 'sleep' => 'sleep', 'hug' => 'hug',
        'pain' => 'pain', 'help' => 'help', 'happy' => 'happy', 'sad' => 'sad', 'scared' => 'scared', 'play' => 'play',
    ];

    private const PALETTE = [
        'water' => '#5fa8e8', 'potty' => '#9aa5d6', 'milk' => '#8fc9d8', 'bed' => '#b9a3e8', 'food' => '#f0a35e', 'sleep' => '#7b8ad6', 'hug' => '#f08fb0',
        'pain' => '#e8604f', 'help' => '#e8604f', 'happy' => '#f2c94c', 'sad' => '#7fb0e0', 'scared' => '#b79be0', 'play' => '#6dc08a',
    ];

    /** @return string PNG bytes */
    public function render(string $name): string
    {
        $s = self::SIZE * self::SCALE;
        $im = imagecreatetruecolor($s, $s);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagealphablending($im, true);

        $bg = $this->hex($im, self::PALETTE[$name]);
        $white = imagecolorallocate($im, 255, 255, 255);
        $ink = imagecolorallocate($im, 38, 30, 48);
        $this->rounded($im, 0, 0, $s, $s, (int) ($s * .22), $bg);
        $this->{self::CARDS[$name]}($im, $s, $white, $ink, $bg);

        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, self::SIZE, self::SIZE, $s, $s);
        ob_start();
        imagepng($out, null, 9);

        return (string) ob_get_clean();
    }

    // ---------- the pictures (coordinates are fractions of the square) ----------

    private function water($im, $s, $white): void
    {
        $x = fn ($f) => (int) ($f * $s);
        imagefilledpolygon($im, [$x(.5), $x(.17), $x(.28), $x(.55), $x(.72), $x(.55)], $white);
        imagefilledellipse($im, $x(.5), $x(.6), $x(.46), $x(.46), $white);
    }

    private function potty($im, $s, $white, $ink): void
    {
        $x = fn ($f) => (int) ($f * $s);
        $this->rounded($im, $x(.34), $x(.2), $x(.66), $x(.48), $x(.04), $white);          // tank
        imagefilledellipse($im, $x(.5), $x(.58), $x(.5), $x(.26), $white);                 // seat
        imagefilledellipse($im, $x(.5), $x(.58), $x(.34), $x(.15), $ink);                  // hole
        $this->rounded($im, $x(.4), $x(.66), $x(.6), $x(.8), $x(.03), $white);             // base
    }

    private function milk($im, $s, $white): void
    {
        $x = fn ($f) => (int) ($f * $s);
        $this->rounded($im, $x(.34), $x(.38), $x(.66), $x(.84), $x(.07), $white);          // bottle
        $this->rounded($im, $x(.31), $x(.3), $x(.69), $x(.42), $x(.04), $white);           // collar
        imagefilledellipse($im, $x(.5), $x(.2), $x(.16), $x(.24), $white);                 // teat
        imagefilledrectangle($im, $x(.34), $x(.52), $x(.66), $x(.55), imagecolorallocatealpha($im, 120, 150, 190, 60));
        imagefilledrectangle($im, $x(.34), $x(.64), $x(.66), $x(.67), imagecolorallocatealpha($im, 120, 150, 190, 60));
    }

    private function bed($im, $s, $white, $ink): void
    {
        $x = fn ($f) => (int) ($f * $s);
        $this->rounded($im, $x(.14), $x(.58), $x(.86), $x(.7), $x(.03), $white);           // mattress
        imagefilledrectangle($im, $x(.14), $x(.34), $x(.2), $x(.8), $white);               // headboard
        imagefilledrectangle($im, $x(.8), $x(.62), $x(.86), $x(.8), $white);               // foot
        $this->rounded($im, $x(.24), $x(.46), $x(.42), $x(.58), $x(.04), $white);          // pillow
        imagefilledrectangle($im, $x(.46), $x(.5), $x(.84), $x(.58), $ink);                // blanket
    }

    private function food($im, $s, $white, $ink): void
    {
        $x = fn ($f) => (int) ($f * $s);
        imagefilledellipse($im, $x(.5), $x(.5), $x(.66), $x(.66), $white);                 // plate
        imagefilledellipse($im, $x(.5), $x(.5), $x(.46), $x(.46), imagecolorallocatealpha($im, 240, 163, 94, 0));
        imagefilledrectangle($im, $x(.13), $x(.3), $x(.16), $x(.7), $white);               // fork
        imagefilledrectangle($im, $x(.1), $x(.3), $x(.19), $x(.4), $white);
        imagefilledrectangle($im, $x(.84), $x(.3), $x(.87), $x(.7), $white);               // knife
    }

    private function sleep($im, $s, $white, $ink, $bg): void
    {
        $x = fn ($f) => (int) ($f * $s);
        imagefilledellipse($im, $x(.44), $x(.52), $x(.54), $x(.54), $white);               // moon
        imagefilledellipse($im, $x(.58), $x(.42), $x(.5), $x(.5), $bg);
        foreach ([[.72, .26, .06], [.8, .46, .04], [.76, .72, .05]] as [$a, $b, $r]) {
            imagefilledellipse($im, $x($a), $x($b), $x($r * 2), $x($r * 2), $white);       // stars
        }
    }

    private function hug($im, $s, $white): void
    {
        $x = fn ($f) => (int) ($f * $s);
        imagefilledellipse($im, $x(.4), $x(.4), $x(.28), $x(.28), $white);                 // heart, two lobes and a point
        imagefilledellipse($im, $x(.6), $x(.4), $x(.28), $x(.28), $white);
        imagefilledpolygon($im, [$x(.27), $x(.47), $x(.73), $x(.47), $x(.5), $x(.76)], $white);
        $this->stroke($im, $x(.5), $x(.56), $x(.34), $x(.2), 15, 165, (int) ($s * .035), $white);   // arms cradling it
    }

    private function face($im, $s, $white, $ink): void
    {
        $x = fn ($f) => (int) ($f * $s);
        imagefilledellipse($im, $x(.5), $x(.5), $x(.72), $x(.72), $white);
        imagefilledellipse($im, $x(.38), $x(.43), $x(.07), $x(.1), $ink);
        imagefilledellipse($im, $x(.62), $x(.43), $x(.07), $x(.1), $ink);
    }

    private function happy($im, $s, $white, $ink): void
    {
        $this->face($im, $s, $white, $ink);
        $this->stroke($im, (int) ($s * .5), (int) ($s * .54), (int) ($s * .17), (int) ($s * .12), 20, 160, (int) ($s * .03), $ink);
    }

    private function sad($im, $s, $white, $ink): void
    {
        $this->face($im, $s, $white, $ink);
        $this->stroke($im, (int) ($s * .5), (int) ($s * .7), (int) ($s * .15), (int) ($s * .09), 200, 340, (int) ($s * .03), $ink);
        imagefilledellipse($im, (int) ($s * .66), (int) ($s * .56), (int) ($s * .05), (int) ($s * .08), imagecolorallocate($im, 95, 168, 232));   // tear
    }

    private function scared($im, $s, $white, $ink): void
    {
        $x = fn ($f) => (int) ($f * $s);
        imagefilledellipse($im, $x(.5), $x(.5), $x(.72), $x(.72), $white);
        foreach ([.37, .63] as $cx) {
            imagefilledellipse($im, $x($cx), $x(.42), $x(.13), $x(.17), $ink);
            imagefilledellipse($im, $x($cx), $x(.4), $x(.04), $x(.05), $white);
        }
        imagefilledellipse($im, $x(.5), $x(.66), $x(.14), $x(.17), $ink);                  // open mouth
    }

    private function pain($im, $s, $white, $ink): void
    {
        $x = fn ($f) => (int) ($f * $s);
        $this->face($im, $s, $white, $ink);
        $this->stroke($im, $x(.5), $x(.7), $x(.15), $x(.09), 200, 340, (int) ($s * .03), $ink);
        $yellow = imagecolorallocate($im, 242, 201, 76);
        imagefilledpolygon($im, [$x(.78), $x(.08), $x(.62), $x(.36), $x(.73), $x(.36), $x(.66), $x(.6), $x(.9), $x(.28), $x(.78), $x(.28), $x(.88), $x(.08)], $yellow);   // flash
    }

    private function help($im, $s, $white): void
    {
        $x = fn ($f) => (int) ($f * $s);
        foreach ([.34, .42, .5, .58] as $i => $fx) {                                       // four fingers and a palm, raised
            $this->rounded($im, $x($fx - .035), $x(.2 + abs($i - 1.5) * .02), $x($fx + .035), $x(.55), $x(.035), $white);
        }
        $this->rounded($im, $x(.3), $x(.46), $x(.66), $x(.78), $x(.08), $white);
        imagefilledpolygon($im, [$x(.3), $x(.62), $x(.18), $x(.48), $x(.25), $x(.44), $x(.36), $x(.56)], $white);                      // thumb
    }

    private function play($im, $s, $white): void
    {
        $x = fn ($f) => (int) ($f * $s);
        imagefilledellipse($im, $x(.5), $x(.5), $x(.64), $x(.64), $white);
        $red = imagecolorallocate($im, 232, 96, 79);
        $blue = imagecolorallocate($im, 95, 168, 232);
        imagefilledarc($im, $x(.5), $x(.5), $x(.64), $x(.64), 200, 340, $red, IMG_ARC_PIE);
        imagefilledarc($im, $x(.5), $x(.5), $x(.64), $x(.64), 20, 160, $blue, IMG_ARC_PIE);
        imagefilledellipse($im, $x(.5), $x(.5), $x(.12), $x(.12), $white);
    }

    // ---------- helpers ----------

    /** A smooth thick curve: round dots laid along an elliptical arc (angles in degrees, 0 is right, 90 is down). */
    private function stroke($im, int $cx, int $cy, int $rx, int $ry, int $from, int $to, int $w, int $color): void
    {
        for ($a = $from; $a <= $to; $a += .5) {
            imagefilledellipse($im, (int) ($cx + $rx * cos(deg2rad($a))), (int) ($cy + $ry * sin(deg2rad($a))), $w, $w, $color);
        }
    }

    private function hex($im, string $hex): int
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return imagecolorallocate($im, $r, $g, $b);
    }

    private function rounded($im, int $x1, int $y1, int $x2, int $y2, int $r, int $color): void
    {
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
        foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $color);
        }
    }
}
