<?php

namespace App\Helpers;

final class ColorHelper
{
    /**
     * Pick a readable foreground color for a hex background.
     */
    public static function contrastForeground(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return 'oklch(0.985 0 0)';
        }

        $red = (int) hexdec(substr($hex, 0, 2));
        $green = (int) hexdec(substr($hex, 2, 2));
        $blue = (int) hexdec(substr($hex, 4, 2));

        $luminance = (0.299 * $red + 0.587 * $green + 0.114 * $blue) / 255;

        return $luminance > 0.6 ? 'oklch(0.205 0 0)' : 'oklch(0.985 0 0)';
    }

    /**
     * Whether the given value is a `#rrggbb` color.
     */
    public static function isHex(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }
}
