<?php

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Registry\PermissionRegistry;
use Illuminate\Support\Facades\Gate;

it('seeds every registered permission', function () {
    expect(Permission::count())->toBe(count(PermissionRegistry::getAllModulePermissions()));
});

it('seeds the system roles', function () {
    foreach (UserRole::cases() as $role) {
        expect(Role::where('name', $role->value)->where('is_system', true)->exists())->toBeTrue();
    }
});

it('seeds the super admin with every permission', function () {
    $superAdmin = User::where('email', 'superadmin@evoriq.test')->first();

    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->hasRole(UserRole::SUPER_ADMIN->value))->toBeTrue()
        ->and($superAdmin->getAllPermissions()->count())->toBe(Permission::count());
});

it('grants the super admin every ability through the gate', function () {
    $superAdmin = User::where('email', 'superadmin@evoriq.test')->firstOrFail();

    expect(Gate::forUser($superAdmin)->allows('delete', User::factory()->create()))->toBeTrue();
});

it('denies members from deleting users', function () {
    $member = User::factory()->create();
    $member->assignRole(UserRole::MEMBER->value);

    expect(Gate::forUser($member)->allows('delete', User::factory()->create()))->toBeFalse();
});

it('allows admins to create users but not update roles', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::ADMIN->value);

    $role = Role::where('name', UserRole::MEMBER->value)->firstOrFail();

    expect(Gate::forUser($admin)->allows('create', User::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('update', $role))->toBeFalse();
});

it('prevents deleting system roles', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('role.delete');

    $role = Role::where('name', UserRole::ADMIN->value)->firstOrFail();

    expect(Gate::forUser($user)->denies('delete', $role))->toBeTrue();
});
