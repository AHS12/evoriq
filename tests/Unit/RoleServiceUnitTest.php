<?php

use App\DTOs\Role\RoleDTO;
use App\DTOs\Role\RoleFilterDTO;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Role\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(RoleRepositoryInterface::class);
    $this->audit = Mockery::mock(AuditLogService::class);
    $this->audit->shouldReceive('record')->byDefault();
    $this->service = new RoleService($this->repository, $this->audit);
});

afterEach(function () {
    Mockery::close();
});

/**
 * Build an unsaved role for service unit tests.
 */
function roleStub(string $name, bool $isSystem = false): Role
{
    $role = new Role;
    $role->name = $name;
    $role->guard_name = 'web';
    $role->is_system = $isSystem;

    return $role;
}

test('create filters permissions and syncs them', function () {
    $role = roleStub('Custom');

    $this->repository->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['name'] === 'Custom'
            && $data['is_system'] === false
            && $data['guard_name'] === 'web'))
        ->andReturn($role);

    $this->repository->shouldReceive('assignablePermissionNames')
        ->once()
        ->with(['user.view', 'settings.view'], false)
        ->andReturn(['user.view']);

    $this->repository->shouldReceive('syncPermissions')
        ->once()
        ->with($role, ['user.view'])
        ->andReturn($role);

    expect($this->service->create(new RoleDTO('Custom', ['user.view', 'settings.view'])))->toBe($role);
});

test('update renames a custom role and syncs its permissions', function () {
    $role = roleStub('Old');

    $this->repository->shouldReceive('update')
        ->once()
        ->with($role, ['name' => 'New'])
        ->andReturn($role);

    $this->repository->shouldReceive('assignablePermissionNames')
        ->once()
        ->with(['user.view'], false)
        ->andReturn(['user.view']);

    $this->repository->shouldReceive('syncPermissions')
        ->once()
        ->with($role, ['user.view'])
        ->andReturn($role);

    $this->service->update($role, new RoleDTO('New', ['user.view']));
});

test('update keeps a system role name but syncs permissions', function () {
    $role = roleStub('Admin', isSystem: true);

    $this->repository->shouldReceive('update')->never();

    $this->repository->shouldReceive('assignablePermissionNames')
        ->once()
        ->with(['report.view', 'settings.view'], true)
        ->andReturn(['report.view', 'settings.view']);

    $this->repository->shouldReceive('syncPermissions')
        ->once()
        ->with($role, ['report.view', 'settings.view'])
        ->andReturn($role);

    $this->service->update($role, new RoleDTO('Renamed', ['report.view', 'settings.view']));
});

test('update refuses to modify the super admin role', function () {
    $role = roleStub('Super Admin', isSystem: true);

    $this->repository->shouldReceive('update')->never();
    $this->repository->shouldReceive('syncPermissions')->never();

    expect(fn () => $this->service->update($role, new RoleDTO('Super Admin', [])))
        ->toThrow(AuthorizationException::class);
});

test('update prevents a non super admin from locking themselves out', function () {
    $actor = makeUserWithPermissions(['role.update']);
    $this->actingAs($actor);

    $role = roleStub('Custom');

    $this->repository->shouldReceive('update')->once()->andReturn($role);
    $this->repository->shouldReceive('isAssignedTo')
        ->once()
        ->with($role, $actor->id)
        ->andReturnTrue();
    $this->repository->shouldReceive('syncPermissions')->never();

    expect(fn () => $this->service->update($role, new RoleDTO('Custom', ['user.view'])))
        ->toThrow(AuthorizationException::class);
});

test('delete refuses to remove a system role', function () {
    $role = roleStub('Admin', isSystem: true);

    $this->repository->shouldReceive('delete')->never();

    expect(fn () => $this->service->delete($role))
        ->toThrow(AuthorizationException::class);
});

test('delete removes a custom role', function () {
    $role = roleStub('Custom');

    $this->repository->shouldReceive('delete')->once()->with($role)->andReturnTrue();

    expect($this->service->delete($role))->toBeTrue();
});

test('paginate delegates to the repository with the filter page size', function () {
    $filters = new RoleFilterDTO(perPage: 25);
    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $this->repository->shouldReceive('paginate')
        ->once()
        ->with($filters, 25, ['permissions'])
        ->andReturn($paginator);

    expect($this->service->paginate($filters))->toBe($paginator);
});

test('permissionCatalog delegates to the repository', function () {
    $catalog = collect();

    $this->repository->shouldReceive('permissionCatalog')->once()->andReturn($catalog);

    expect($this->service->permissionCatalog())->toBe($catalog);
});
