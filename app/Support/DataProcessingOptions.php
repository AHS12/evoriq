<?php

namespace App\Support;

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportFormat;
use App\Models\Role;

/**
 * The select/filter options shared by the Job Center page and the module-level
 * import/export dialogs.
 */
final class DataProcessingOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function make(): array
    {
        return [
            'entities' => array_map(fn (DataEntity $entity): array => [
                'value' => $entity->value,
                'label' => $entity->label(),
                'icon' => $entity->icon(),
                'export' => $entity->supports(DataProcessingJobType::EXPORT),
                'import' => $entity->supports(DataProcessingJobType::IMPORT),
                'report' => $entity->supports(DataProcessingJobType::REPORT),
                'import_headings' => $entity->importHeadings(),
                'import_column_help' => $entity->importColumnHelp(),
            ], DataEntity::cases()),
            'formats' => array_map(fn (ExportFormat $format): array => [
                'value' => $format->value,
                'label' => $format->label(),
            ], ExportFormat::cases()),
            'statuses' => array_map(fn (DataProcessingJobStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ], DataProcessingJobStatus::cases()),
            'types' => array_map(fn (DataProcessingJobType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
            ], DataProcessingJobType::cases()),
            'roles' => Role::query()->orderBy('name')->pluck('name')->all(),
        ];
    }
}
