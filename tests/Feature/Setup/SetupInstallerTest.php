<?php

use App\Enums\CacheDriver;
use App\Enums\QueueDriver;
use App\Enums\SessionDriver;
use App\Models\User;
use App\Services\Setup\DatabaseConfigurator;
use App\Services\Setup\EnvironmentWriter;
use App\Services\Setup\SetupService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    // Start from a truly uninstalled state: the suite seeds a super admin.
    User::query()->delete();

    $this->envPath = tempnam(sys_get_temp_dir(), 'evoriq_env_');
    file_put_contents($this->envPath, "APP_NAME=Evoriq\n");

    $this->app->instance(EnvironmentWriter::class, new EnvironmentWriter($this->envPath));
});

afterEach(function () {
    if (isset($this->envPath) && is_file($this->envPath)) {
        @unlink($this->envPath);
    }
});

it('generates an application key', function () {
    $this->from(route('setup.index'))
        ->post(route('setup.environment'))
        ->assertRedirect(route('setup.index'))
        ->assertSessionHasNoErrors();

    expect((new EnvironmentWriter($this->envPath))->get('APP_KEY'))
        ->toStartWith('base64:');
});

it('validates the database payload', function () {
    $this->from(route('setup.index'))
        ->post(route('setup.database'), [
            'driver' => 'pgsql',
            'host' => '',
            'port' => '',
            'database' => '',
            'username' => '',
        ])
        ->assertSessionHasErrors(['host', 'port', 'database', 'username']);
});

it('saves the database configuration', function () {
    $this->mock(DatabaseConfigurator::class, function ($mock) {
        $mock->shouldReceive('provision')
            ->once()
            ->andReturn(['ok' => true, 'created' => false, 'message' => 'Connected.']);

        $mock->shouldReceive('write')->once();
    });

    $this->from(route('setup.index'))
        ->post(route('setup.database'), [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'evoriq',
            'username' => 'postgres',
            'password' => 'secret',
            'create_database' => true,
        ])
        ->assertRedirect(route('setup.index'))
        ->assertSessionHasNoErrors();
});

it('rejects an unreachable database', function () {
    $this->mock(DatabaseConfigurator::class, function ($mock) {
        $mock->shouldReceive('provision')
            ->once()
            ->andReturn(['ok' => false, 'created' => false, 'message' => 'Connection refused.']);
    });

    $this->from(route('setup.index'))
        ->post(route('setup.database'), [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 5432,
            'database' => 'evoriq',
            'username' => 'postgres',
            'password' => 'secret',
        ])
        ->assertSessionHasErrors('database');
});

it('saves the runtime drivers', function () {
    $this->from(route('setup.index'))
        ->post(route('setup.drivers'), [
            'session' => SessionDriver::DATABASE->value,
            'cache' => CacheDriver::DATABASE->value,
            'queue' => QueueDriver::DATABASE->value,
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
        ])
        ->assertRedirect(route('setup.index'))
        ->assertSessionHasNoErrors();

    $writer = new EnvironmentWriter($this->envPath);

    expect($writer->get('SESSION_DRIVER'))->toBe('database')
        ->and($writer->get('CACHE_STORE'))->toBe('database')
        ->and($writer->get('QUEUE_CONNECTION'))->toBe('database');
});

it('runs the migrations and seeders', function () {
    $this->from(route('setup.index'))
        ->post(route('setup.migrate'))
        ->assertRedirect(route('setup.index'))
        ->assertSessionHasNoErrors();

    expect(app(SetupService::class)->migrated())->toBeTrue()
        ->and(app(SetupService::class)->seeded())->toBeTrue();
});
