<?php

use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use App\Services\Sync\Contracts\SyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Tests\TestCase;

uses(TestCase::class);

test('a registered handler resolves by entity type', function () {
    $registry = new SyncHandlerRegistry;
    $registry->register(SyncEntityType::CLIENTS, RegistryFakeHandler::class);

    expect($registry->has(SyncEntityType::CLIENTS))->toBeTrue()
        ->and($registry->has(SyncEntityType::TAGS))->toBeFalse()
        ->and($registry->resolve(SyncEntityType::CLIENTS))->toBeInstanceOf(RegistryFakeHandler::class);
});

test('resolving an unregistered entity throws', function () {
    $registry = new SyncHandlerRegistry;

    expect(fn () => $registry->resolve(SyncEntityType::TAGS))
        ->toThrow(RuntimeException::class);
});

test('handlers are registered from the clockify config', function () {
    config(['clockify.handlers' => [
        SyncEntityType::TAGS->value => RegistryFakeHandler::class,
    ]]);

    $registry = new SyncHandlerRegistry;
    $registry->registerFromConfig();

    expect($registry->has(SyncEntityType::TAGS))->toBeTrue();
});

class RegistryFakeHandler implements SyncHandler
{
    public function entityType(): SyncEntityType
    {
        return SyncEntityType::CLIENTS;
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    public function fetchPage(SyncContext $context, int $page): iterable
    {
        return [];
    }

    public function map(array $raw, SyncContext $context): array
    {
        return $raw;
    }

    public function repository(): SyncUpsertRepositoryInterface
    {
        throw new RuntimeException('No repository.');
    }

    public function delete(SyncContext $context, string $clockifyId): void {}
}
