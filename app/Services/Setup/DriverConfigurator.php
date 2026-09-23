<?php

namespace App\Services\Setup;

use App\DTOs\Setup\DriverConfigDTO;
use App\Enums\CacheDriver;
use App\Enums\QueueDriver;
use App\Enums\SessionDriver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Detects Redis and persists the session/cache/queue drivers.
 *
 * Setup infrastructure: it probes the Redis server and writes the runtime
 * driver configuration, falling back to database/file drivers when Redis is
 * unavailable.
 */
class DriverConfigurator
{
    public function __construct(
        protected EnvironmentWriter $writer,
    ) {}

    /**
     * @return array{session: list<array{value: string, label: string}>, cache: list<array{value: string, label: string}>, queue: list<array{value: string, label: string}>}
     */
    public function options(): array
    {
        return [
            'session' => SessionDriver::options(),
            'cache' => CacheDriver::options(),
            'queue' => QueueDriver::options(),
        ];
    }

    /**
     * Whether Redis is reachable with the available client.
     *
     * @return array{available: bool, client: string|null, message: string}
     */
    public function redis(): array
    {
        $client = $this->client();

        if ($client === null) {
            return [
                'available' => false,
                'client' => null,
                'message' => 'No Redis client available (install the redis extension or predis/predis).',
            ];
        }

        config(['database.redis.client' => $client]);

        try {
            Redis::connection()->ping();

            return ['available' => true, 'client' => $client, 'message' => 'Redis is reachable.'];
        } catch (Throwable) {
            return ['available' => false, 'client' => $client, 'message' => 'Redis is not reachable.'];
        }
    }

    /**
     * The recommended driver set for this server.
     *
     * @return array{session: string, cache: string, queue: string, redis: bool}
     */
    public function recommended(): array
    {
        if ($this->redis()['available']) {
            return [
                'session' => SessionDriver::REDIS->value,
                'cache' => CacheDriver::REDIS->value,
                'queue' => QueueDriver::REDIS->value,
                'redis' => true,
            ];
        }

        return [
            'session' => SessionDriver::DATABASE->value,
            'cache' => CacheDriver::DATABASE->value,
            'queue' => QueueDriver::DATABASE->value,
            'redis' => false,
        ];
    }

    /**
     * Current environment values, used as form defaults.
     *
     * @return array{session: string, cache: string, queue: string, redisHost: string, redisPort: int, redisPassword: string}
     */
    public function current(): array
    {
        $values = $this->writer->read();

        return [
            'session' => $values['SESSION_DRIVER'] ?? (string) config('session.driver', 'file'),
            'cache' => $values['CACHE_STORE'] ?? (string) config('cache.default', 'file'),
            'queue' => $values['QUEUE_CONNECTION'] ?? (string) config('queue.default', 'database'),
            'redisHost' => $values['REDIS_HOST'] ?? '127.0.0.1',
            'redisPort' => (int) ($values['REDIS_PORT'] ?? 6379),
            'redisPassword' => '',
        ];
    }

    /**
     * Persist the driver configuration and reload it in the current process.
     */
    public function write(DriverConfigDTO $dto): void
    {
        $values = [
            'SESSION_DRIVER' => $dto->session->value,
            'CACHE_STORE' => $dto->cache->value,
            'QUEUE_CONNECTION' => $dto->queue->value,
        ];

        if ($dto->usesRedis()) {
            $values['REDIS_HOST'] = $dto->redisHost;
            $values['REDIS_PORT'] = $dto->redisPort;
            $values['REDIS_PASSWORD'] = $dto->redisPassword;
            $values['REDIS_CLIENT'] = $this->client() ?? 'phpredis';
        }

        $this->writer->set($values);

        try {
            Artisan::call('config:clear');
        } catch (Throwable) {
            // The runtime config is updated manually below regardless.
        }

        config([
            'session.driver' => $dto->session->value,
            'cache.default' => $dto->cache->value,
            'queue.default' => $dto->queue->value,
        ]);

        if ($dto->usesRedis()) {
            config([
                'database.redis.default.host' => $dto->redisHost,
                'database.redis.default.port' => $dto->redisPort,
                'database.redis.default.password' => $dto->redisPassword,
                'database.redis.cache.host' => $dto->redisHost,
                'database.redis.cache.port' => $dto->redisPort,
                'database.redis.cache.password' => $dto->redisPassword,
            ]);
        }
    }

    /**
     * The best available Redis client, or null when none is installed.
     */
    public function client(): ?string
    {
        if (extension_loaded('redis')) {
            return 'phpredis';
        }

        if (class_exists('Predis\\Client')) {
            return 'predis';
        }

        return null;
    }
}
