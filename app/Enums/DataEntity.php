<?php

namespace App\Enums;

use App\Exports\Contracts\Exportable;
use App\Exports\UserExport;
use App\Imports\Contracts\Importable;
use App\Imports\UserImport;
use App\Models\DataProcessingJob;
use Closure;

/**
 * The registry of entities the Data Processing Center can operate on.
 *
 * Adding a new entity = one case here + an exporter (and later an importer),
 * with no changes to the Job Center UI.
 */
enum DataEntity: string
{
    case USERS = 'users';

    public function label(): string
    {
        return match ($this) {
            self::USERS => 'Users',
        };
    }

    /**
     * The lucide-react icon name used by the frontend.
     */
    public function icon(): string
    {
        return match ($this) {
            self::USERS => 'users',
        };
    }

    /**
     * The permission-module prefix, e.g. USERS => 'user' so the module-level
     * permissions are `user.export` / `user.import`.
     */
    public function permissionKey(): string
    {
        return match ($this) {
            self::USERS => 'user',
        };
    }

    /**
     * Whether this entity supports the given operation type.
     */
    public function supports(DataProcessingJobType $type): bool
    {
        return match ($this) {
            self::USERS => in_array($type, [DataProcessingJobType::IMPORT, DataProcessingJobType::EXPORT], true),
        };
    }

    /**
     * The export formats this entity can produce.
     *
     * @return array<int, ExportFormat>
     */
    public function formats(): array
    {
        return match ($this) {
            self::USERS => [ExportFormat::CSV, ExportFormat::XLSX],
        };
    }

    /**
     * The import template headings.
     *
     * @return array<int, string>
     */
    public function importHeadings(): array
    {
        return match ($this) {
            self::USERS => ['Name', 'Email', 'Roles'],
        };
    }

    /**
     * An example row shown in the downloadable import template.
     *
     * @return array<int, string>
     */
    public function importSampleRow(): array
    {
        return match ($this) {
            self::USERS => ['Ada Lovelace', 'ada@example.com', 'Member'],
        };
    }

    /**
     * Per-column help shown next to the template download.
     *
     * @return array<string, string>
     */
    public function importColumnHelp(): array
    {
        return match ($this) {
            self::USERS => [
                'Name' => 'Required, max 255 characters',
                'Email' => 'Required, unique, valid email',
                'Roles' => 'Optional, comma-separated role names',
            ],
        };
    }

    /**
     * Build the exporter that produces this entity's file.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function makeExporter(array $parameters): Exportable
    {
        return match ($this) {
            self::USERS => new UserExport($parameters),
        };
    }

    /**
     * Build the importer that consumes this entity's file.
     *
     * @param  (Closure(int, string|null): void)|null  $onProgress
     */
    public function makeImporter(DataProcessingJob $job, ?Closure $onProgress = null): Importable
    {
        return match ($this) {
            self::USERS => new UserImport($job, $onProgress),
        };
    }
}
