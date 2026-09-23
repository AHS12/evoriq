<?php

namespace App\Enums;

enum ExportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';

    /**
     * The file extension for the format.
     */
    public function extension(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::CSV => 'CSV',
            self::XLSX => 'Excel (XLSX)',
        };
    }
}
