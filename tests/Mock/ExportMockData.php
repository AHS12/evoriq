<?php

namespace Tests\Mock;

use App\Enums\ExportEntity;
use App\Enums\ExportFormat;

class ExportMockData
{
    /**
     * A valid export request payload.
     *
     * @return array<string, mixed>
     */
    public static function request(): array
    {
        return [
            'entity_type' => ExportEntity::USERS->value,
            'format' => ExportFormat::XLSX->value,
        ];
    }

    /**
     * An export request carrying search filters.
     *
     * @return array<string, mixed>
     */
    public static function withFilters(): array
    {
        return [
            ...self::request(),
            'filters' => ['search' => 'ada'],
        ];
    }
}
