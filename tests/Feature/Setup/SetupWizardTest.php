<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Setup\SetupService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    // Start from a truly uninstalled state: the suite seeds a super admin.
    User::query()->delete();
});

it('renders the setup wizard while the app is not installed', function () {
    $this->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/index')
            ->has('requirements')
            ->has('status')
            ->has('database.drivers')
            ->has('database.current')
            ->has('drivers.options')
            ->has('drivers.recommended')
            ->has('defaults.appName'));
});

it('redirects normal requests to setup while not installed', function () {
    $this->get('/dashboard')->assertRedirect(route('setup.index'));
});

it('creates the super admin, verifies them and locks the app', function () {
    $response = $this->post(route('setup.store'), [
        'app_name' => 'Acme Analytics',
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

    expect($user->hasRole(UserRole::SUPER_ADMIN->value))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Storage::disk('local')->exists(SetupService::LOCK_FILE))->toBeTrue();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
});

it('skips admin creation when a super admin already exists', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::SUPER_ADMIN->value);

    // Re-open the wizard the way `php artisan setup:reset` does.
    app(SetupService::class)->reset();

    $this->get(route('setup.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('setup/index')
            ->where('hasSuperAdmin', true)
            ->where('existingAdmin.email', $admin->email));

    $this->post(route('setup.store'), ['app_name' => 'Acme Analytics'])
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::query()->count())->toBe(1);

    $this->assertAuthenticatedAs($admin);
    expect(Storage::disk('local')->exists(SetupService::RESET_MARKER))->toBeFalse();
});

it('cannot be run twice', function () {
    User::factory()->create()->assignRole(UserRole::SUPER_ADMIN->value);

    $this->get(route('setup.index'))->assertRedirect(route('login'));
});

it('validates the super admin payload', function () {
    $this->post(route('setup.store'), [
        'app_name' => '',
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'secret-password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors(['app_name', 'name', 'email', 'password']);
});
