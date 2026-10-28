<?php

namespace App\Support;

/**
 * The on-screen keyboard a child uses for "spell a word": the alphabet in order (a, b, c ... and alif, bay, pay ...),
 * not the QWERTY order of a phone or computer. Children learn the alphabet in sequence, so the keys follow it.
 * The server owns the letters so the app and the portal preview always agree and a lesson cannot ask for a letter the child cannot type.
 */
class ChildKeyboard
{
    public const ENGLISH = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];

    /** The Urdu alphabet in its usual order, followed by the extra forms that real words need (alif madd, noon ghunna, hamza forms). */
    public const URDU = ['ا', 'آ', 'ب', 'پ', 'ت', 'ٹ', 'ث', 'ج', 'چ', 'ح', 'خ', 'د', 'ڈ', 'ذ', 'ر', 'ڑ', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'ں', 'و', 'ؤ', 'ہ', 'ھ', 'ء', 'ی', 'ئ', 'ے'];

    /** @return array<string, array{direction: string, columns: int, letters: list<string>}> */
    public static function layouts(): array
    {
        return [
            'english' => ['direction' => 'ltr', 'columns' => 7, 'letters' => self::ENGLISH],
            'urdu' => ['direction' => 'rtl', 'columns' => 8, 'letters' => self::URDU],
        ];
    }

    /** Which keyboard a word needs: Urdu if it is written in Arabic script, otherwise English. */
    public static function layoutFor(?string $word): string
    {
        return preg_match('/\p{Arabic}/u', (string) $word) ? 'urdu' : 'english';
    }

    /** Letters of the word that the child's keyboard does not have (spaces, zer/zabar marks and joiners are ignored). */
    public static function unsupported(?string $word): array
    {
        $word = (string) $word;
        $layout = self::layoutFor($word);
        $word = preg_replace('/[\s\x{200C}\x{200D}\x{064B}-\x{065F}\x{0670}]+/u', '', $word) ?? '';
        $word = $layout === 'english' ? mb_strtolower($word) : $word;
        $keys = $layout === 'urdu' ? self::URDU : self::ENGLISH;

        $bad = [];
        foreach (mb_str_split($word) as $ch) {
            if (! in_array($ch, $keys, true)) {
                $bad[$ch] = $ch;
            }
        }

        return array_values($bad);
    }
}
