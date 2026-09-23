<?php

namespace App\Services\Clockify;

use Closure;
use Generator;
use Illuminate\Http\Client\Response;

/**
 * Iterates a Clockify list endpoint one page at a time.
 *
 * A synchronization plan never assumes that a date range maps to a single
 * request, so pagination is handled independently using the "page",
 * "page-size" parameters and the "Last-Page" response header.
 */
class ClockifyPaginator
{
    public function __construct(
        protected int $pageSize = 200,
        protected int $maxPageSize = 5000,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @param  Closure(string, array<string, mixed>): Response  $fetch
     * @return Generator<int, array<int, mixed>>
     */
    public function pages(string $uri, array $query, Closure $fetch): Generator
    {
        $page = max(1, (int) ($query['page'] ?? 1));

        $pageSize = (int) ($query['page-size'] ?? $this->pageSize);
        $pageSize = max(1, min($pageSize, $this->maxPageSize));

        while (true) {
            $response = $fetch($uri, array_merge($query, [
                'page' => $page,
                'page-size' => $pageSize,
            ]));

            $items = $response->json();

            if (! is_array($items) || $items === []) {
                return;
            }

            yield $page => $items;

            if ($this->isLastPage($response) || count($items) < $pageSize) {
                return;
            }

            $page++;
        }
    }

    protected function isLastPage(Response $response): bool
    {
        return filter_var($response->header('Last-Page'), FILTER_VALIDATE_BOOL);
    }
}
