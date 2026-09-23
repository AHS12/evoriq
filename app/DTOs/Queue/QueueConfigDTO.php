<?php

namespace App\DTOs\Queue;

readonly class QueueConfigDTO
{
    public function __construct(
        public string $name,
        public int $tries,
        public int $timeout,
    ) {}
}
