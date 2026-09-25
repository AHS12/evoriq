<?php

use App\DTOs\User\UserDTO;
use App\DTOs\User\UserFilterDTO;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitation;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\User\UserService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(UserRepositoryInterface::class);
    $this->audit = Mockery::mock(AuditLogService::class);
    $this->audit->shouldReceive('record')->byDefault();
    $this->service = new UserService($this->repository, $this->audit);
});

afterEach(function () {
    Mockery::close();
});

test('create provisions an invited user and sends the invitation', function () {
    Notification::fake();

    $user = User::factory()->invited()->create(['email' => 'new@example.com']);

    $this->repository->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['status'] === UserStatus::INVITED
            && $data['email'] === 'new@example.com'
            && $data['invitation_token'] !== null))
        ->andReturn($user);

    $this->repository->shouldReceive('assignRoles')
        ->once()
        ->with($user, ['Member'])
        ->andReturn($user);

    $result = $this->service->create(new UserDTO('New User', 'new@example.com', ['Member']));

    expect($result)->toBe($user);
    Notification::assertSentTo($user, UserInvitation::class);
});

test('update syncs roles for a regular user', function () {
    $user = User::factory()->create();

    $this->repository->shouldReceive('update')->once()->andReturn($user);
    $this->repository->shouldReceive('assignRoles')
        ->once()
        ->with($user, ['Admin'])
        ->andReturn($user);

    $this->service->update($user, new UserDTO('Name', 'user@example.com', ['Admin'], UserStatus::ACTIVE));
});

test('update never changes the roles of a super admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    $this->repository->shouldReceive('update')->once()->andReturn($superAdmin);
    $this->repository->shouldReceive('assignRoles')->never();

    $this->service->update($superAdmin, new UserDTO('Name', 'super@example.com', ['Admin'], UserStatus::ACTIVE));
});

test('updateStatus re-issues the invitation when reverting to invited', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->with($user, Mockery::on(fn (array $data): bool => $data['status'] === UserStatus::INVITED
            && $data['invitation_token'] !== null))
        ->andReturn($user);

    $this->service->updateStatus($user, UserStatus::INVITED);

    Notification::assertSentTo($user, UserInvitation::class);
});

test('acceptInvitation activates, verifies and clears the invitation', function () {
    $user = User::factory()->invited()->create();

    $this->repository->shouldReceive('update')
        ->once()
        ->with($user, Mockery::on(fn (array $data): bool => $data['status'] === UserStatus::ACTIVE
            && $data['invitation_token'] === null
            && $data['email_verified_at'] !== null))
        ->andReturn($user);

    $this->service->acceptInvitation($user, 'Password123!');
});

test('delete refuses to remove the super admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(UserRole::SUPER_ADMIN->value);

    $this->repository->shouldReceive('delete')->never();

    expect(fn () => $this->service->delete($superAdmin))
        ->toThrow(AuthorizationException::class);
});

test('delete removes a regular user', function () {
    $user = User::factory()->create();

    $this->repository->shouldReceive('delete')->once()->with($user)->andReturnTrue();

    expect($this->service->delete($user))->toBeTrue();
});

test('paginate delegates to the repository with the filter page size and roles', function () {
    $filters = new UserFilterDTO(perPage: 25);
    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $this->repository->shouldReceive('paginate')
        ->once()
        ->with($filters, 25, ['roles'])
        ->andReturn($paginator);

    expect($this->service->paginate($filters))->toBe($paginator);
});
