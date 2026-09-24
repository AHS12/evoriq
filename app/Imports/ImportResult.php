<?php

namespace App\Imports;

/**
 * An immutable summary of what an import did.
 */
final readonly class ImportResult
{
    /**
     * @param  array<int, array{row: int, type: string, message: string}>  $errors
     */
    public function __construct(
        public int $processed = 0,
        public int $created = 0,
        public int $skipped = 0,
        public int $failed = 0,
        public array $errors = [],
    ) {}

    /**
     * Whether the import produced any skipped/failed rows worth reporting.
     */
    public function hasIssues(): bool
    {
        return $this->errors !== [];
    }
}
