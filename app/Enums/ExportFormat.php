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

    /**
     * The MIME type of a generated file in this format.
     */
    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
    }
}
