<?php

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobType;
use App\Models\DataProcessingJob;
use App\Models\Permission;
use Illuminate\Support\Facades\Gate;

test('the data processing permissions are seeded', function () {
    foreach ([
        'data-processing.view',
        'data-processing.view.all',
        'data-processing.manage',
        'data-processing.delete',
        'import.create',
        'user.export',
        'user.import',
    ] as $permission) {
        expect(Permission::where('name', $permission)->exists())->toBeTrue();
    }
});

test('members receive the data processing view and user import/export permissions', function () {
    $member = member();

    expect($member->hasPermissionTo('data-processing.view'))->toBeTrue()
        ->and($member->hasPermissionTo('user.export'))->toBeTrue()
        ->and($member->hasPermissionTo('user.import'))->toBeTrue()
        ->and($member->hasPermissionTo('data-processing.view.all'))->toBeFalse();
});

test('admins receive the full data processing permission set', function () {
    $admin = admin();

    expect($admin->hasPermissionTo('data-processing.view'))->toBeTrue()
        ->and($admin->hasPermissionTo('data-processing.view.all'))->toBeTrue()
        ->and($admin->hasPermissionTo('data-processing.manage'))->toBeTrue()
        ->and($admin->hasPermissionTo('data-processing.delete'))->toBeTrue()
        ->and($admin->hasPermissionTo('import.create'))->toBeTrue()
        ->and($admin->hasPermissionTo('user.import'))->toBeTrue();
});

test('module-level permissions allow operating on their entity only', function () {
    $exporter = makeUserWithPermissions(['user.export']);
    $importer = makeUserWithPermissions(['user.import']);
    $none = makeUserWithPermissions(['file.view']);

    expect(Gate::forUser($exporter)->allows('create', [DataProcessingJob::class, DataProcessingJobType::EXPORT, DataEntity::USERS]))->toBeTrue()
        ->and(Gate::forUser($exporter)->allows('create', [DataProcessingJob::class, DataProcessingJobType::IMPORT, DataEntity::USERS]))->toBeFalse()
        ->and(Gate::forUser($importer)->allows('create', [DataProcessingJob::class, DataProcessingJobType::IMPORT, DataEntity::USERS]))->toBeTrue()
        ->and(Gate::forUser($none)->allows('create', [DataProcessingJob::class, DataProcessingJobType::EXPORT, DataEntity::USERS]))->toBeFalse();
});

test('the global create permission allows any entity', function () {
    $exporter = makeUserWithPermissions(['export.create']);

    expect(Gate::forUser($exporter)->allows('create', [DataProcessingJob::class, DataProcessingJobType::EXPORT, DataEntity::USERS]))->toBeTrue();
});
