<?php

namespace App\Enums;

enum AppearanceContrast: string
{
    case NORMAL = 'normal';
    case HIGH = 'high';

    public function label(): string
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
        };
    }
}
