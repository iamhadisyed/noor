<?php

namespace App\Support;


/**
 * Urdu rendering for DomPDF, which cannot shape or reorder Arabic-script text reliably.
 *
 * Each label is converted to joined presentation forms in visual (left-to-right) order and drawn
 * with GD/FreeType into a transparent PNG, so glyph order is exactly what we give it.
 * Uses Noto Naskh Arabic (SIL OFL) bundled in resources/fonts.
 */
class UrduText
{
    public const FONT_BOLD = 'NotoNaskhArabic_700Bold.ttf';

    /** Visual-order, joined presentation forms for a logical-order Urdu string. */
    public static function shape(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        return UrduShaper::visual($text);
    }

    /** PNG data URI of the label, trimmed to its ink bounds; null if GD/FreeType or the font is unavailable. */
    public static function imageDataUri(?string $text, int $px = 96, string $hexColor = '#E07A5F'): ?string
    {
        $font = resource_path('fonts/' . self::FONT_BOLD);
        $text = self::shape($text);
        if ($text === '' || ! function_exists('imagettftext') || ! is_file($font)) {
            return null;
        }

        $box = imagettfbbox($px, 0, $font, $text);
        $pad = (int) ($px * 0.25);
        $w = (int) (abs($box[2] - $box[0]) + $pad * 2);
        $h = (int) (abs($box[7] - $box[1]) + $pad * 2);

        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagealphablending($im, true);

        [$r, $g, $b] = sscanf(ltrim($hexColor, '#'), '%02x%02x%02x');
        imagettftext($im, $px, 0, $pad - $box[0], $pad - $box[7], imagecolorallocate($im, $r, $g, $b), $font, $text);

        ob_start();
        imagepng($im);
        $png = ob_get_clean();

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
