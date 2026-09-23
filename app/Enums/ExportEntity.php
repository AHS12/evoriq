<?php

namespace App\Enums;

use App\Exports\Contracts\Exportable;
use App\Exports\UserExport;

enum ExportEntity: string
{
    case USERS = 'users';

    public function label(): string
    {
        return match ($this) {
            self::USERS => 'Users',
        };
    }

    /**
     * Build the exporter that produces this entity's file.
     *
     * @param  array<string, mixed>  $filters
     */
    public function makeExporter(array $filters): Exportable
    {
        return match ($this) {
            self::USERS => new UserExport($filters),
        };
    }
}
