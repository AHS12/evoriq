<?php

namespace App\Enums;

use App\Exports\AuditLogExport;
use App\Exports\Contracts\Exportable;
use App\Exports\UserExport;
use App\Imports\Contracts\Importable;
use App\Imports\UserImport;
use App\Models\DataProcessingJob;
use Closure;
use RuntimeException;

/**
 * The registry of entities the Data Processing Center can operate on.
 *
 * Adding a new entity = one case here + an exporter (and later an importer),
 * with no changes to the Job Center UI.
 */
enum DataEntity: string
{
    case USERS = 'users';

    case AUDIT_LOGS = 'audit-logs';

    public function label(): string
    {
        return match ($this) {
            self::USERS => 'Users',
            self::AUDIT_LOGS => 'Audit Log',
        };
    }

    /**
     * The lucide-react icon name used by the frontend.
     */
    public function icon(): string
    {
        return match ($this) {
            self::USERS => 'users',
            self::AUDIT_LOGS => 'history',
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
            self::AUDIT_LOGS => 'audit',
        };
    }

    /**
     * Whether this entity supports the given operation type.
     */
    public function supports(DataProcessingJobType $type): bool
    {
        return match ($this) {
            self::USERS => in_array($type, [DataProcessingJobType::IMPORT, DataProcessingJobType::EXPORT], true),
            self::AUDIT_LOGS => $type === DataProcessingJobType::EXPORT,
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
            self::AUDIT_LOGS => [ExportFormat::CSV, ExportFormat::XLSX],
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
            self::AUDIT_LOGS => [],
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
            self::AUDIT_LOGS => [],
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
            self::AUDIT_LOGS => [],
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
            self::AUDIT_LOGS => new AuditLogExport($parameters),
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
            self::AUDIT_LOGS => throw new RuntimeException('Audit logs cannot be imported.'),
        };
    }
}
