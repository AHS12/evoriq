<?php

use App\Enums\AuditEvent;
use App\Enums\SyncRunStatus;
use App\Models\ClockifyConnection;
use App\Models\ClockifySyncRun;
use App\Models\ClockifyWorkspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'clockify.retry.times' => 1,
        'clockify.retry.sleep' => 0,
        'clockify.sync_concurrency' => 10,
    ]);
});

/**
 * @return array{0: ClockifyConnection, 1: ClockifyWorkspace}
 */
function importWizardWorkspace(): array
{
    $connection = ClockifyConnection::factory()->free()->create(['workspace_id' => 'ws-1']);
    $workspace = ClockifyWorkspace::factory()
        ->for($connection, 'connection')
        ->create(['clockify_id' => 'ws-1']);

    return [$connection, $workspace];
}

function importWizardUsers(): void
{
    Http::fake([
        '*/workspaces/ws-1/users*' => Http::response(
            [['id' => 'u1', 'name' => 'Ada']],
            200,
            ['Last-Page' => 'true'],
        ),
    ]);
}

test('the import wizard requires permission and renders', function () {
    $user = makeUserWithPermissions(['sync.trigger']);

    $this->actingAs($user)
        ->get(route('import.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('import/index')
            ->has('connection')
            ->has('activeImport')
            ->has('maxHistoryYears')
            ->has('regions'));
});

test('a user without the wizard permission is forbidden', function () {
    $user = makeUserWithPermissions(['report.view']);

    $this->actingAs($user)
        ->get(route('import.index'))
        ->assertForbidden();
});

test('starting an import creates a run and redirects to its timeline', function () {
    $user = makeUserWithPermissions(['sync.trigger']);
    importWizardWorkspace();
    importWizardUsers();
    Queue::fake();

    $this->actingAs($user)
        ->post(route('import.store'), [
            'range_start' => now()->subMonths(6)->toDateString(),
            'range_end' => now()->toDateString(),
        ])
        ->assertRedirect(route('import.show', ClockifySyncRun::query()->firstOrFail()));

    $run = ClockifySyncRun::query()->firstOrFail();

    expect($run->status)->toBe(SyncRunStatus::RUNNING)
        ->and($run->mode->value)->toBe('initial')
        ->and($run->jobs()->count())->toBeGreaterThan(0);

    expect(DB::table('activity_log')->where('event', AuditEvent::IMPORT_STARTED->value)->exists())
        ->toBeTrue();
});

test('a second concurrent import is redirected to the active one', function () {
    $user = makeUserWithPermissions(['sync.trigger']);
    [$connection] = importWizardWorkspace();
    importWizardUsers();
    Queue::fake();

    $active = ClockifySyncRun::factory()
        ->for($connection, 'connection')
        ->running()
        ->create();

    $this->actingAs($user)
        ->post(route('import.store'), [
            'range_start' => now()->subMonth()->toDateString(),
            'range_end' => now()->toDateString(),
        ])
        ->assertRedirect(route('import.show', $active));

    expect(ClockifySyncRun::query()->count())->toBe(1);
});

test('the import range is validated against the history horizon', function () {
    $user = makeUserWithPermissions(['sync.trigger']);
    importWizardWorkspace();

    $this->actingAs($user)
        ->post(route('import.store'), [
            'range_start' => now()->subYears(10)->toDateString(),
            'range_end' => now()->toDateString(),
        ])
        ->assertSessionHasErrors('range_start');

    expect(ClockifySyncRun::query()->count())->toBe(0);
});

test('the run timeline renders for a sync run', function () {
    $user = makeUserWithPermissions(['sync.trigger']);
    [$connection] = importWizardWorkspace();

    $run = ClockifySyncRun::factory()
        ->for($connection, 'connection')
        ->completed()
        ->create();

    $this->actingAs($user)
        ->get(route('import.show', $run))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('import/show')
            ->where('run.run_type', 'sync')
            ->where('run.status', 'completed')
            ->has('events')
            ->has('eventsMeta'));
});
