<?php

namespace App\Services\Sync;

use App\Enums\SyncEntityType;
use App\Services\Sync\Contracts\SyncHandler;
use RuntimeException;

/**
 * Resolves a `SyncEntityType` to its `SyncHandler` (SYNC-04). Each entity spec
 * (ENT-*) registers its handler here, so adding an entity never touches the
 * runner.
 */
class SyncHandlerRegistry
{
    /**
     * @var array<string, class-string<SyncHandler>>
     */
    protected array $handlers = [];

    /**
     * @param  class-string<SyncHandler>  $handler
     */
    public function register(SyncEntityType $entityType, string $handler): void
    {
        $this->handlers[$entityType->value] = $handler;
    }

    /**
     * Register every handler declared in `config('clockify.handlers')`, so
     * adding an entity is one handler class plus one config entry (ENT-00).
     */
    public function registerFromConfig(): void
    {
        /** @var array<string, class-string<SyncHandler>> $handlers */
        $handlers = (array) config('clockify.handlers', []);

        foreach ($handlers as $entityType => $handler) {
            $this->register(SyncEntityType::from((string) $entityType), $handler);
        }
    }

    public function has(SyncEntityType $entityType): bool
    {
        return isset($this->handlers[$entityType->value]);
    }

    public function resolve(SyncEntityType $entityType): SyncHandler
    {
        $handler = $this->handlers[$entityType->value] ?? null;

        if ($handler === null) {
            throw new RuntimeException("No sync handler is registered for [{$entityType->value}].");
        }

        return app($handler);
    }
}
