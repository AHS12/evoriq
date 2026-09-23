<?php

use App\Models\DataProcessingJob;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('the super admin bypasses every gate', function () {
    $superAdmin = superAdmin();

    expect(Gate::forUser($superAdmin)->allows('create', User::class))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('viewAny', DataProcessingJob::class))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('viewAny', Upload::class))->toBeTrue()
        ->and(Gate::forUser($superAdmin)->allows('viewDeveloperTools'))->toBeTrue();
});

test('members cannot delete exports or files', function () {
    $member = member();

    expect(Gate::forUser($member)->allows('delete', DataProcessingJob::factory()->create()))->toBeFalse()
        ->and(Gate::forUser($member)->allows('delete', Upload::factory()->create()))->toBeFalse();
});

test('members can view and create exports', function () {
    $member = member();

    expect(Gate::forUser($member)->allows('viewAny', DataProcessingJob::class))->toBeTrue()
        ->and(Gate::forUser($member)->allows('create', DataProcessingJob::class))->toBeTrue();
});

test('users without developer.view cannot access developer tools', function () {
    expect(Gate::forUser(member())->allows('viewDeveloperTools'))->toBeFalse();
});

test('developer.view grants access to developer tools', function () {
    $user = makeUserWithPermissions(['developer.view']);

    expect(Gate::forUser($user)->allows('viewDeveloperTools'))->toBeTrue();
});

test('file.view is scoped to the file owner', function () {
    $owner = makeUserWithPermissions(['file.view', 'file.create']);
    $other = makeUserWithPermissions(['file.view']);

    $ownedUpload = Upload::factory()->create(['created_by' => $owner->id]);
    $othersUpload = Upload::factory()->create(['created_by' => $other->id]);

    expect(Gate::forUser($owner)->allows('view', $ownedUpload))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('view', $othersUpload))->toBeFalse();
});

test('file.view.all can view any file', function () {
    $user = makeUserWithPermissions(['file.view.all']);
    $upload = Upload::factory()->create();

    expect(Gate::forUser($user)->allows('view', $upload))->toBeTrue();
});
