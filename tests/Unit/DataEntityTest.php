<?php

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportFormat;
use App\Exports\UserExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('users entity exposes its metadata', function () {
    $entity = DataEntity::USERS;

    expect($entity->label())->toBe('Users')
        ->and($entity->icon())->toBe('users')
        ->and($entity->permissionKey())->toBe('user')
        ->and($entity->formats())->toBe([ExportFormat::CSV, ExportFormat::XLSX])
        ->and($entity->importHeadings())->toBe(['Name', 'Email', 'Roles'])
        ->and($entity->importSampleRow())->toBe(['Ada Lovelace', 'ada@example.com', 'Member'])
        ->and($entity->importColumnHelp())->toHaveKeys(['Name', 'Email', 'Roles']);
});

test('users entity supports import and export but not report', function () {
    expect(DataEntity::USERS->supports(DataProcessingJobType::IMPORT))->toBeTrue()
        ->and(DataEntity::USERS->supports(DataProcessingJobType::EXPORT))->toBeTrue()
        ->and(DataEntity::USERS->supports(DataProcessingJobType::REPORT))->toBeFalse();
});

test('users entity builds a user exporter', function () {
    expect(DataEntity::USERS->makeExporter([]))->toBeInstanceOf(UserExport::class);
});

test('job types expose labels and icons', function () {
    expect(DataProcessingJobType::IMPORT->label())->toBe('Import')
        ->and(DataProcessingJobType::EXPORT->label())->toBe('Export')
        ->and(DataProcessingJobType::REPORT->label())->toBe('Report')
        ->and(DataProcessingJobType::EXPORT->icon())->toBe('download')
        ->and(DataProcessingJobType::IMPORT->isFileGenerating())->toBeFalse()
        ->and(DataProcessingJobType::REPORT->isFileGenerating())->toBeTrue();
});

test('cancelled is a final status', function () {
    expect(DataProcessingJobStatus::CANCELLED->isFinal())->toBeTrue()
        ->and(DataProcessingJobStatus::COMPLETED->isFinal())->toBeTrue()
        ->and(DataProcessingJobStatus::PENDING->isActive())->toBeTrue()
        ->and(DataProcessingJobStatus::CANCELLED->isActive())->toBeFalse();
});
