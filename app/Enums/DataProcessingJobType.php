<?php

namespace App\Enums;

enum DataProcessingJobType: string
{
    case IMPORT = 'import';
    case EXPORT = 'export';
    case REPORT = 'report';

    public function label(): string
    {
        return match ($this) {
            self::IMPORT => 'Import',
            self::EXPORT => 'Export',
            self::REPORT => 'Report',
        };
    }

    /**
     * The lucide-react icon name used by the frontend for this type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::IMPORT => 'upload',
            self::EXPORT => 'download',
            self::REPORT => 'file-text',
        };
    }

    /**
     * Whether the operation produces a downloadable file artifact.
     */
    public function isFileGenerating(): bool
    {
        return $this !== self::IMPORT;
    }
}
