<?php

namespace App\DTOs\Sync;

/**
 * One page of the Entity Changes feed (SYNC-06). The feed paginates with its
 * own `page`/`limit`, not `Last-Page`, so `hasMore` is inferred from a full page.
 */
final readonly class EntityChangePage
{
    /**
     * @param  array<int, EntityChangeDTO>  $items
     */
    public function __construct(
        public array $items,
        public int $page = 0,
        public bool $hasMore = false,
    ) {}

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
