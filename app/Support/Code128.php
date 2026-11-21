<?php

namespace App\Support;

/**
 * Minimal Code 128 (set B) barcode renderer producing inline SVG for printable labels (STK05).
 */
class Code128
{
    /**
     * Bar and space widths for symbol values 0 to 105, then the stop pattern.
     *
     * @var list<string>
     */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    private const QUIET_ZONE_MODULES = 10;

    public static function svg(string $text, int $height = 40, float $moduleWidth = 1.0): string
    {
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        $codes = [self::START_B];
        $checksum = self::START_B;

        foreach (str_split($text) as $position => $character) {
            $value = ord($character) - 32;
            $codes[] = $value;
            $checksum += ($position + 1) * $value;
        }

        $codes[] = $checksum % 103;
        $codes[] = self::STOP;

        $x = self::QUIET_ZONE_MODULES;
        $bars = '';

        foreach ($codes as $code) {
            $isBar = true;

            foreach (str_split(self::PATTERNS[$code]) as $width) {
                if ($isBar) {
                    $bars .= sprintf('<rect x="%s" y="0" width="%s" height="%d"/>', $x * $moduleWidth, (int) $width * $moduleWidth, $height);
                }

                $x += (int) $width;
                $isBar = ! $isBar;
            }
        }

        $totalWidth = ($x + self::QUIET_ZONE_MODULES) * $moduleWidth;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %s %d" preserveAspectRatio="none" role="img" aria-label="%s"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g></svg>',
            $totalWidth,
            $height,
            e($text),
            $bars,
        );
    }
}
