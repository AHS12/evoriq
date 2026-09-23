<?php

use App\Jobs\Developer\RunMaintenanceCommand;
use App\Models\CommandRun;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('shows the developer page to authorized users', function () {
    $user = makeUserWithPermissions(['developer.view']);

    $this->actingAs($user)
        ->get(route('admin.settings.developer.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/developer')
            ->has('tools', 3)
            ->has('health')
            ->has('system')
            ->has('maintenance')
            ->where('maintenance.enabled', false)
            ->where('maintenance.mode', false)
            ->has('maintenance.runs'));
});

test('forbids users without the developer permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.developer.edit'))
        ->assertForbidden();
});

test('allows the super admin to access developer tools', function () {
    $user = User::where('email', 'superadmin@evoriq.test')->firstOrFail();

    $this->actingAs($user)
        ->get(route('admin.settings.developer.edit'))
        ->assertOk();
});

test('exposes maintenance actions when enabled', function () {
    config(['developer.maintenance_actions' => true]);

    $this->actingAs(superAdmin())
        ->get(route('admin.settings.developer.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('maintenance.enabled', true)
            ->has('maintenance.actions', 7));
});

test('exposes recent command runs', function () {
    config(['developer.maintenance_actions' => true]);

    CommandRun::factory()->completed()->create(['action' => 'cache-clear']);

    $this->actingAs(superAdmin())
        ->get(route('admin.settings.developer.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maintenance.runs', 1)
            ->where('maintenance.runs.0.action', 'cache-clear')
            ->where('maintenance.runs.0.status', 'completed'));
});

test('super admins can queue a maintenance action', function () {
    config(['developer.maintenance_actions' => true]);
    Queue::fake();

    $this->actingAs(superAdmin())
        ->from(route('admin.settings.developer.edit'))
        ->post(route('admin.settings.developer.maintenance', ['action' => 'cache-clear']))
        ->assertRedirect(route('admin.settings.developer.edit'));

    Queue::assertPushed(RunMaintenanceCommand::class);

    $this->assertDatabaseHas('command_runs', [
        'action' => 'cache-clear',
        'command' => 'cache:clear',
        'status' => 'pending',
    ]);
});

test('unknown maintenance actions return not found', function () {
    config(['developer.maintenance_actions' => true]);
    Queue::fake();

    $this->actingAs(superAdmin())
        ->post(route('admin.settings.developer.maintenance', ['action' => 'nope']))
        ->assertNotFound();

    Queue::assertNothingPushed();
});

test('maintenance actions are hidden when disabled', function () {
    config(['developer.maintenance_actions' => false]);

    $this->actingAs(superAdmin())
        ->post(route('admin.settings.developer.maintenance', ['action' => 'cache-clear']))
        ->assertNotFound();
});

test('non super admins cannot run maintenance actions', function () {
    config(['developer.maintenance_actions' => true]);

    $user = makeUserWithPermissions(['developer.view']);

    $this->actingAs($user)
        ->post(route('admin.settings.developer.maintenance', ['action' => 'cache-clear']))
        ->assertForbidden();
});

test('super admins can toggle maintenance mode', function () {
    config(['developer.maintenance_actions' => true]);

    try {
        $this->actingAs(superAdmin())
            ->from(route('admin.settings.developer.edit'))
            ->post(route('admin.settings.developer.maintenance-mode'), ['enabled' => true])
            ->assertRedirect(route('admin.settings.developer.edit'));

        expect(app()->isDownForMaintenance())->toBeTrue();

        $this->actingAs(superAdmin())
            ->post(route('admin.settings.developer.maintenance-mode'), ['enabled' => false])
            ->assertRedirect();

        expect(app()->isDownForMaintenance())->toBeFalse();
    } finally {
        Artisan::call('up');
    }
});

test('maintenance mode requires a boolean', function () {
    config(['developer.maintenance_actions' => true]);

    $this->actingAs(superAdmin())
        ->post(route('admin.settings.developer.maintenance-mode'), [])
        ->assertSessionHasErrors('enabled');
});
