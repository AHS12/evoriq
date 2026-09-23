<?php

namespace App\Enums;

enum AppearanceMode: string
{
    case LIGHT = 'light';
    case DARK = 'dark';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::LIGHT => 'Light',
            self::DARK => 'Dark',
            self::SYSTEM => 'System',
        };
    }
}
