<?php

use App\Http\Resources\Connection\ConnectionResource;
use App\Models\ClockifyConnection;
use App\Models\Organization;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

test('the resource never leaks credentials', function () {
    $connection = ClockifyConnection::factory()->create([
        'api_key' => 'top-secret-key',
        'addon_token' => 'top-secret-addon',
    ]);

    $array = ConnectionResource::make($connection)->resolve();

    expect($array)->not->toHaveKey('api_key')
        ->and($array)->not->toHaveKey('addon_token')
        ->and($array)->toHaveKeys(['id', 'name', 'region', 'status', 'plan', 'workspace', 'last_verified_at', 'webhook_limit']);

    $encoded = json_encode($array);

    expect($encoded)->not->toContain('top-secret-key')
        ->and($encoded)->not->toContain('top-secret-addon');
});

test('the policy gates connection actions by permission', function () {
    $manager = makeUserWithPermissions([
        'connection.view',
        'connection.create',
        'connection.update',
        'connection.delete',
    ]);
    $viewer = makeUserWithPermissions(['connection.view']);
    $outsider = makeUserWithPermissions(['file.view']);

    $connection = ClockifyConnection::factory()->create();

    expect(Gate::forUser($manager)->allows('viewAny', ClockifyConnection::class))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('viewAny', ClockifyConnection::class))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('viewAny', ClockifyConnection::class))->toBeFalse()
        ->and(Gate::forUser($viewer)->allows('create', ClockifyConnection::class))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('create', ClockifyConnection::class))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('update', $connection))->toBeTrue()
        ->and(Gate::forUser($viewer)->allows('delete', $connection))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('delete', $connection))->toBeTrue();
});

test('connections are scoped to the current organization and resolvable per organization', function () {
    Organization::forgetDefault();
    app(OrganizationContext::class)->reset();

    $other = Organization::factory()->create();

    $mine = ClockifyConnection::factory()->create();
    $theirs = ClockifyConnection::factory()->create(['organization_id' => $other->id]);

    expect(ClockifyConnection::query()->pluck('id')->all())->toBe([$mine->id]);

    $repository = app(ClockifyConnectionRepositoryInterface::class);

    expect($repository->forOrganization($other->id)->pluck('id')->all())->toBe([$theirs->id])
        ->and($repository->findActive()?->id)->toBe($mine->id)
        ->and($repository->findActive((int) $other->id)?->id)->toBe($theirs->id);
});
