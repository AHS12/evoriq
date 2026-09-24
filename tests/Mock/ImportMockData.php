<?php

namespace Tests\Mock;

use App\Enums\DataEntity;

class ImportMockData
{
    /**
     * A CSV with a header, two valid rows, an in-file duplicate and an invalid
     * email (four data rows in total).
     */
    public static function csv(): string
    {
        return implode("\n", [
            'Name,Email,Roles',
            'Ada Lovelace,ada@example.com,Member',
            'Grace Hopper,grace@example.com,Admin',
            'Ada Again,ada@example.com,',
            'Bad Row,not-an-email,',
        ]);
    }

    /**
     * A valid import request payload.
     *
     * @return array<string, mixed>
     */
    public static function request(): array
    {
        return [
            'entity_type' => DataEntity::USERS->value,
            'filters' => ['send_invitations' => false],
        ];
    }
}
