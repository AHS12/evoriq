<?php

namespace App\Registry;

use App\DTOs\Queue\QueueConfigDTO;
use App\Enums\QueueName;

class QueueRegistry
{
    /**
     * Resolve the retry/timeout configuration for a queue channel.
     */
    public static function get(QueueName $queue): QueueConfigDTO
    {
        /** @var array<string, array<string, mixed>> $channels */
        $channels = (array) config('queue.channels', []);

        $config = $channels[$queue->value] ?? [];

        return new QueueConfigDTO(
            name: $queue->value,
            tries: (int) ($config['tries'] ?? 0),
            timeout: (int) ($config['timeout'] ?? 0),
        );
    }
}
