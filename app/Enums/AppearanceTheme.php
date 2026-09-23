<?php

namespace App\Enums;

enum AppearanceTheme: string
{
    case DEFAULT = 'default';
    case DRACULA = 'dracula';
    case KHAKI = 'khaki';

    public function label(): string
    {
        return match ($this) {
            self::DEFAULT => 'Default',
            self::DRACULA => 'Dracula',
            self::KHAKI => 'Khaki',
        };
    }
}
