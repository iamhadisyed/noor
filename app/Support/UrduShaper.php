<?php

namespace App\Support;

/**
 * Minimal Arabic-script shaper for Urdu labels (no external dependency).
 *
 * Converts logical-order text to joined Unicode presentation forms in visual (left-to-right) order,
 * ready to be drawn glyph-by-glyph by a renderer that does no shaping or bidi of its own (GD/FreeType).
 * Combining marks (harakat) are dropped; Latin/digit runs keep their own left-to-right order.
 * The form table is generated from the Unicode decomposition data for U+0621..U+06FF.
 */
class UrduShaper
{
    /** base code point => isolated / final / initial / medial presentation-form code points */
    private const FORMS = [
        0x0621 => ['iso' => 0xFE80],
        0x0622 => ['iso' => 0xFE81, 'f' => 0xFE82],
        0x0623 => ['iso' => 0xFE83, 'f' => 0xFE84],
        0x0624 => ['iso' => 0xFE85, 'f' => 0xFE86],
        0x0625 => ['iso' => 0xFE87, 'f' => 0xFE88],
        0x0626 => ['iso' => 0xFE89, 'f' => 0xFE8A, 'i' => 0xFE8B, 'm' => 0xFE8C],
        0x0627 => ['iso' => 0xFE8D, 'f' => 0xFE8E],
        0x0628 => ['iso' => 0xFE8F, 'f' => 0xFE90, 'i' => 0xFE91, 'm' => 0xFE92],
        0x0629 => ['iso' => 0xFE93, 'f' => 0xFE94],
        0x062A => ['iso' => 0xFE95, 'f' => 0xFE96, 'i' => 0xFE97, 'm' => 0xFE98],
        0x062B => ['iso' => 0xFE99, 'f' => 0xFE9A, 'i' => 0xFE9B, 'm' => 0xFE9C],
        0x062C => ['iso' => 0xFE9D, 'f' => 0xFE9E, 'i' => 0xFE9F, 'm' => 0xFEA0],
        0x062D => ['iso' => 0xFEA1, 'f' => 0xFEA2, 'i' => 0xFEA3, 'm' => 0xFEA4],
        0x062E => ['iso' => 0xFEA5, 'f' => 0xFEA6, 'i' => 0xFEA7, 'm' => 0xFEA8],
        0x062F => ['iso' => 0xFEA9, 'f' => 0xFEAA],
        0x0630 => ['iso' => 0xFEAB, 'f' => 0xFEAC],
        0x0631 => ['iso' => 0xFEAD, 'f' => 0xFEAE],
        0x0632 => ['iso' => 0xFEAF, 'f' => 0xFEB0],
        0x0633 => ['iso' => 0xFEB1, 'f' => 0xFEB2, 'i' => 0xFEB3, 'm' => 0xFEB4],
        0x0634 => ['iso' => 0xFEB5, 'f' => 0xFEB6, 'i' => 0xFEB7, 'm' => 0xFEB8],
        0x0635 => ['iso' => 0xFEB9, 'f' => 0xFEBA, 'i' => 0xFEBB, 'm' => 0xFEBC],
        0x0636 => ['iso' => 0xFEBD, 'f' => 0xFEBE, 'i' => 0xFEBF, 'm' => 0xFEC0],
        0x0637 => ['iso' => 0xFEC1, 'f' => 0xFEC2, 'i' => 0xFEC3, 'm' => 0xFEC4],
        0x0638 => ['iso' => 0xFEC5, 'f' => 0xFEC6, 'i' => 0xFEC7, 'm' => 0xFEC8],
        0x0639 => ['iso' => 0xFEC9, 'f' => 0xFECA, 'i' => 0xFECB, 'm' => 0xFECC],
        0x063A => ['iso' => 0xFECD, 'f' => 0xFECE, 'i' => 0xFECF, 'm' => 0xFED0],
        0x0641 => ['iso' => 0xFED1, 'f' => 0xFED2, 'i' => 0xFED3, 'm' => 0xFED4],
        0x0642 => ['iso' => 0xFED5, 'f' => 0xFED6, 'i' => 0xFED7, 'm' => 0xFED8],
        0x0643 => ['iso' => 0xFED9, 'f' => 0xFEDA, 'i' => 0xFEDB, 'm' => 0xFEDC],
        0x0644 => ['iso' => 0xFEDD, 'f' => 0xFEDE, 'i' => 0xFEDF, 'm' => 0xFEE0],
        0x0645 => ['iso' => 0xFEE1, 'f' => 0xFEE2, 'i' => 0xFEE3, 'm' => 0xFEE4],
        0x0646 => ['iso' => 0xFEE5, 'f' => 0xFEE6, 'i' => 0xFEE7, 'm' => 0xFEE8],
        0x0647 => ['iso' => 0xFEE9, 'f' => 0xFEEA, 'i' => 0xFEEB, 'm' => 0xFEEC],
        0x0648 => ['iso' => 0xFEED, 'f' => 0xFEEE],
        0x0649 => ['iso' => 0xFEEF, 'f' => 0xFEF0, 'i' => 0xFBE8, 'm' => 0xFBE9],
        0x064A => ['iso' => 0xFEF1, 'f' => 0xFEF2, 'i' => 0xFEF3, 'm' => 0xFEF4],
        0x0671 => ['iso' => 0xFB50, 'f' => 0xFB51],
        0x0677 => ['iso' => 0xFBDD],
        0x0679 => ['iso' => 0xFB66, 'f' => 0xFB67, 'i' => 0xFB68, 'm' => 0xFB69],
        0x067A => ['iso' => 0xFB5E, 'f' => 0xFB5F, 'i' => 0xFB60, 'm' => 0xFB61],
        0x067B => ['iso' => 0xFB52, 'f' => 0xFB53, 'i' => 0xFB54, 'm' => 0xFB55],
        0x067E => ['iso' => 0xFB56, 'f' => 0xFB57, 'i' => 0xFB58, 'm' => 0xFB59],
        0x067F => ['iso' => 0xFB62, 'f' => 0xFB63, 'i' => 0xFB64, 'm' => 0xFB65],
        0x0680 => ['iso' => 0xFB5A, 'f' => 0xFB5B, 'i' => 0xFB5C, 'm' => 0xFB5D],
        0x0683 => ['iso' => 0xFB76, 'f' => 0xFB77, 'i' => 0xFB78, 'm' => 0xFB79],
        0x0684 => ['iso' => 0xFB72, 'f' => 0xFB73, 'i' => 0xFB74, 'm' => 0xFB75],
        0x0686 => ['iso' => 0xFB7A, 'f' => 0xFB7B, 'i' => 0xFB7C, 'm' => 0xFB7D],
        0x0687 => ['iso' => 0xFB7E, 'f' => 0xFB7F, 'i' => 0xFB80, 'm' => 0xFB81],
        0x0688 => ['iso' => 0xFB88, 'f' => 0xFB89],
        0x068C => ['iso' => 0xFB84, 'f' => 0xFB85],
        0x068D => ['iso' => 0xFB82, 'f' => 0xFB83],
        0x068E => ['iso' => 0xFB86, 'f' => 0xFB87],
        0x0691 => ['iso' => 0xFB8C, 'f' => 0xFB8D],
        0x0698 => ['iso' => 0xFB8A, 'f' => 0xFB8B],
        0x06A4 => ['iso' => 0xFB6A, 'f' => 0xFB6B, 'i' => 0xFB6C, 'm' => 0xFB6D],
        0x06A6 => ['iso' => 0xFB6E, 'f' => 0xFB6F, 'i' => 0xFB70, 'm' => 0xFB71],
        0x06A9 => ['iso' => 0xFB8E, 'f' => 0xFB8F, 'i' => 0xFB90, 'm' => 0xFB91],
        0x06AD => ['iso' => 0xFBD3, 'f' => 0xFBD4, 'i' => 0xFBD5, 'm' => 0xFBD6],
        0x06AF => ['iso' => 0xFB92, 'f' => 0xFB93, 'i' => 0xFB94, 'm' => 0xFB95],
        0x06B1 => ['iso' => 0xFB9A, 'f' => 0xFB9B, 'i' => 0xFB9C, 'm' => 0xFB9D],
        0x06B3 => ['iso' => 0xFB96, 'f' => 0xFB97, 'i' => 0xFB98, 'm' => 0xFB99],
        0x06BA => ['iso' => 0xFB9E, 'f' => 0xFB9F],
        0x06BB => ['iso' => 0xFBA0, 'f' => 0xFBA1, 'i' => 0xFBA2, 'm' => 0xFBA3],
        0x06BE => ['iso' => 0xFBAA, 'f' => 0xFBAB, 'i' => 0xFBAC, 'm' => 0xFBAD],
        0x06C0 => ['iso' => 0xFBA4, 'f' => 0xFBA5],
        0x06C1 => ['iso' => 0xFBA6, 'f' => 0xFBA7, 'i' => 0xFBA8, 'm' => 0xFBA9],
        0x06C5 => ['iso' => 0xFBE0, 'f' => 0xFBE1],
        0x06C6 => ['iso' => 0xFBD9, 'f' => 0xFBDA],
        0x06C7 => ['iso' => 0xFBD7, 'f' => 0xFBD8],
        0x06C8 => ['iso' => 0xFBDB, 'f' => 0xFBDC],
        0x06C9 => ['iso' => 0xFBE2, 'f' => 0xFBE3],
        0x06CB => ['iso' => 0xFBDE, 'f' => 0xFBDF],
        0x06CC => ['iso' => 0xFBFC, 'f' => 0xFBFD, 'i' => 0xFBFE, 'm' => 0xFBFF],
        0x06D0 => ['iso' => 0xFBE4, 'f' => 0xFBE5, 'i' => 0xFBE6, 'm' => 0xFBE7],
        0x06D2 => ['iso' => 0xFBAE, 'f' => 0xFBAF],
        0x06D3 => ['iso' => 0xFBB0, 'f' => 0xFBB1],
    ];

    /** lam + alef-variant ligatures: [isolated, final] */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    public static function visual(string $text): string
    {
        $tokens = preg_split('/(\s+)/u', trim($text), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $out = [];
        foreach ($tokens as $token) {
            $out[] = preg_match('/\p{Arabic}/u', $token) ? self::shapeWord($token) : $token;
        }

        return implode('', array_reverse($out));
    }

    /** Shapes one whitespace-free token and returns it in visual order. */
    private static function shapeWord(string $word): string
    {
        $cps = [];
        foreach (mb_str_split($word) as $ch) {
            $cp = mb_ord($ch);
            if (($cp >= 0x064B && $cp <= 0x065F) || $cp === 0x0670) {
                continue; // combining marks
            }
            $cps[] = $cp;
        }

        $glyphs = [];
        $n = count($cps);
        for ($i = 0; $i < $n; $i++) {
            $cp = $cps[$i];
            $prevJoins = $i > 0 && self::joinsForward($cps[$i - 1]);

            if ($cp === 0x0644 && $i + 1 < $n && isset(self::LAM_ALEF[$cps[$i + 1]])) {
                $glyphs[] = self::LAM_ALEF[$cps[$i + 1]][$prevJoins ? 1 : 0];
                $i++;
                continue;
            }

            $f = self::FORMS[$cp] ?? null;
            if ($f === null) {
                $glyphs[] = $cp; // non-letter or ZWNJ: passes through and breaks joining
                continue;
            }

            $nextJoins = $i + 1 < $n && isset($f['i']) && self::joinsBackward($cps[$i + 1]);
            $key = match (true) {
                $prevJoins && $nextJoins => 'm',
                $prevJoins => 'f',
                $nextJoins => 'i',
                default => 'iso',
            };
            $glyphs[] = $f[$key] ?? $f['iso'] ?? $cp;
        }

        return implode('', array_map(fn ($g) => mb_chr($g), array_reverse($glyphs)));
    }

    /** Can this letter connect to the letter that follows it? (dual-joining letters only) */
    private static function joinsForward(int $cp): bool
    {
        return isset(self::FORMS[$cp]['i']);
    }

    /** Can this letter connect to the letter before it? */
    private static function joinsBackward(int $cp): bool
    {
        return isset(self::FORMS[$cp]['f']);
    }
}
