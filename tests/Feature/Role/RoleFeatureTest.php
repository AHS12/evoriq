<?php

use App\Enums\UserRole;
use App\Models\Role;
use Inertia\Testing\AssertableInertia as Assert;

test('lists roles for those allowed to view all', function () {
    $this->actingAs(superAdmin())
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roles/index')
            ->has('roles.data')
            ->has('permissions')
            ->has('permissions.0.name'));
});

test('forbids members from listing roles', function () {
    $this->actingAs(member())
        ->get(route('roles.index'))
        ->assertForbidden();
});

test('creates a custom role with permissions', function () {
    $this->actingAs(superAdmin())
        ->post(route('roles.store'), [
            'name' => 'Project Manager',
            'permissions' => ['user.view', 'report.view'],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::query()->where('name', 'Project Manager')->firstOrFail();

    expect($role->is_system)->toBeFalse()
        ->and($role->hasPermissionTo('user.view'))->toBeTrue()
        ->and($role->hasPermissionTo('report.view'))->toBeTrue();
});

test('drops system permissions from custom roles', function () {
    $this->actingAs(superAdmin())
        ->post(route('roles.store'), [
            'name' => 'Support',
            'permissions' => ['user.view', 'settings.view'],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::query()->where('name', 'Support')->firstOrFail();

    expect($role->hasPermissionTo('user.view'))->toBeTrue()
        ->and($role->hasPermissionTo('settings.view'))->toBeFalse();
});

test('validates the store payload', function () {
    $this->actingAs(superAdmin())
        ->post(route('roles.store'), ['name' => '', 'permissions' => ['nope']])
        ->assertSessionHasErrors(['name', 'permissions.0']);
});

test('rejects duplicate role names', function () {
    $this->actingAs(superAdmin())
        ->post(route('roles.store'), ['name' => UserRole::ADMIN->value])
        ->assertSessionHasErrors('name');
});

test('updates a custom role name and permissions', function () {
    $role = Role::create(['name' => 'Old Name', 'guard_name' => 'web']);

    $this->actingAs(superAdmin())
        ->patch(route('roles.update', $role), [
            'name' => 'New Name',
            'permissions' => ['user.create'],
        ])
        ->assertRedirect(route('roles.index'));

    $role->refresh();

    expect($role->name)->toBe('New Name')
        ->and($role->hasPermissionTo('user.create'))->toBeTrue();
});

test('updates a system role permissions but not its name', function () {
    $role = Role::query()->where('name', UserRole::ADMIN->value)->firstOrFail();

    $this->actingAs(superAdmin())
        ->patch(route('roles.update', $role), [
            'name' => 'Renamed Admin',
            'permissions' => ['report.view', 'settings.view'],
        ])
        ->assertRedirect(route('roles.index'));

    $role->refresh();

    expect($role->name)->toBe(UserRole::ADMIN->value)
        ->and($role->hasPermissionTo('report.view'))->toBeTrue()
        ->and($role->hasPermissionTo('settings.view'))->toBeTrue();
});

test('the super admin role cannot be modified', function () {
    $role = Role::query()->where('name', UserRole::SUPER_ADMIN->value)->firstOrFail();

    $this->actingAs(superAdmin())
        ->patch(route('roles.update', $role), [
            'name' => UserRole::SUPER_ADMIN->value,
            'permissions' => [],
        ])
        ->assertForbidden();
});

test('deletes a custom role', function () {
    $role = Role::create(['name' => 'Temp', 'guard_name' => 'web']);

    $this->actingAs(superAdmin())
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});

test('cannot delete a system role', function () {
    $role = Role::query()->where('name', UserRole::ADMIN->value)->firstOrFail();

    $this->actingAs(superAdmin())
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();

    $this->assertDatabaseHas('roles', ['id' => $role->id]);
});

test('admins can create roles but cannot update or delete them', function () {
    $role = Role::create(['name' => 'Custom', 'guard_name' => 'web']);

    $this->actingAs(admin())
        ->post(route('roles.store'), ['name' => 'Admin Made'])
        ->assertRedirect(route('roles.index'));

    $this->actingAs(admin())
        ->patch(route('roles.update', $role), ['name' => 'Nope'])
        ->assertForbidden();

    $this->actingAs(admin())
        ->delete(route('roles.destroy', $role))
        ->assertForbidden();
});
