<?php

namespace App\Enums;

enum MediaCollection: string
{
    case PROFILE = 'profile';
    case UPLOAD = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::PROFILE => 'Profile',
            self::UPLOAD => 'Upload',
        };
    }
}
