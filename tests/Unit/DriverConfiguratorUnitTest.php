<?php

use App\DTOs\Setup\DriverConfigDTO;
use App\Enums\CacheDriver;
use App\Enums\QueueDriver;
use App\Enums\SessionDriver;
use App\Services\Setup\DriverConfigurator;
use App\Services\Setup\EnvironmentWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->envPath = tempnam(sys_get_temp_dir(), 'evoriq_env_');
    file_put_contents($this->envPath, "APP_NAME=Evoriq\n");

    $this->writer = new EnvironmentWriter($this->envPath);
    $this->configurator = new DriverConfigurator($this->writer);
});

afterEach(function () {
    if (isset($this->envPath) && is_file($this->envPath)) {
        @unlink($this->envPath);
    }
});

test('it exposes the available driver options', function () {
    $options = $this->configurator->options();

    expect(array_column($options['session'], 'value'))->toBe(['file', 'database', 'redis'])
        ->and(array_column($options['queue'], 'value'))->toBe(['sync', 'database', 'redis']);
});

test('it recommends redis only when reachable', function () {
    $recommended = $this->configurator->recommended();
    $available = $this->configurator->redis()['available'];

    expect($recommended['redis'])->toBe($available)
        ->and($recommended['session'])->toBe($available ? 'redis' : 'database');
});

test('it writes database drivers to the environment file', function () {
    $this->configurator->write(new DriverConfigDTO(
        session: SessionDriver::DATABASE,
        cache: CacheDriver::DATABASE,
        queue: QueueDriver::DATABASE,
        redisHost: '127.0.0.1',
        redisPort: 6379,
        redisPassword: null,
    ));

    expect($this->writer->get('SESSION_DRIVER'))->toBe('database')
        ->and($this->writer->get('CACHE_STORE'))->toBe('database')
        ->and($this->writer->get('QUEUE_CONNECTION'))->toBe('database')
        ->and(config('session.driver'))->toBe('database');
});

test('it writes redis connection details when redis is used', function () {
    $this->configurator->write(new DriverConfigDTO(
        session: SessionDriver::REDIS,
        cache: CacheDriver::REDIS,
        queue: QueueDriver::REDIS,
        redisHost: 'redis.internal',
        redisPort: 6380,
        redisPassword: 'secret',
    ));

    expect($this->writer->get('REDIS_HOST'))->toBe('redis.internal')
        ->and($this->writer->get('REDIS_PORT'))->toBe('6380')
        ->and($this->writer->get('REDIS_PASSWORD'))->toBe('secret');
});
