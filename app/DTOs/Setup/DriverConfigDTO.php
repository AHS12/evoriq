<?php

namespace App\DTOs\Setup;

use App\Enums\CacheDriver;
use App\Enums\QueueDriver;
use App\Enums\SessionDriver;
use App\Http\Requests\Setup\StoreDriversRequest;

final readonly class DriverConfigDTO
{
    public function __construct(
        public SessionDriver $session,
        public CacheDriver $cache,
        public QueueDriver $queue,
        public string $redisHost,
        public int $redisPort,
        public ?string $redisPassword,
    ) {}

    public static function fromRequest(StoreDriversRequest $request): self
    {
        $data = $request->validated();
        $password = $data['redis_password'] ?? null;

        return new self(
            session: SessionDriver::from($data['session']),
            cache: CacheDriver::from($data['cache']),
            queue: QueueDriver::from($data['queue']),
            redisHost: (string) ($data['redis_host'] ?? '127.0.0.1'),
            redisPort: (int) ($data['redis_port'] ?? 6379),
            redisPassword: is_string($password) && $password !== '' ? $password : null,
        );
    }

    /**
     * Whether any of the configured drivers relies on Redis.
     */
    public function usesRedis(): bool
    {
        return $this->session === SessionDriver::REDIS
            || $this->cache === CacheDriver::REDIS
            || $this->queue === QueueDriver::REDIS;
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'session' => $this->session->value,
            'cache' => $this->cache->value,
            'queue' => $this->queue->value,
            'redis_host' => $this->redisHost,
            'redis_port' => $this->redisPort,
            'redis_password' => $this->redisPassword,
        ];
    }
}
