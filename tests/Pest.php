<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a user, optionally assigning one of the seeded system roles.
 */
function makeUser(?UserRole $role = null): User
{
    $user = User::factory()->create();

    if ($role !== null) {
        $user->assignRole($role->value);
    }

    return $user;
}

/**
 * Create a user holding the given permissions.
 *
 * @param  array<int, string>  $permissions
 */
function makeUserWithPermissions(array $permissions, ?UserRole $role = null): User
{
    $user = makeUser($role);
    $user->givePermissionTo($permissions);

    return $user;
}

/**
 * The seeded super admin.
 */
function superAdmin(): User
{
    return User::query()->where('email', 'superadmin@evoriq.test')->firstOrFail();
}

/**
 * Create an admin user.
 */
function admin(): User
{
    return makeUser(UserRole::ADMIN);
}

/**
 * Create a member user.
 */
function member(): User
{
    return makeUser(UserRole::MEMBER);
}
