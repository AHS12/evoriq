<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'Super Admin';
    case ADMIN = 'Admin';
    case MEMBER = 'Member';

    public function label(): string
    {
        return $this->value;
    }
}
