<?php

use App\DTOs\Setup\DatabaseConfigDTO;
use App\Enums\DatabaseDriver;
use App\Services\Setup\DatabaseConfigurator;
use App\Services\Setup\EnvironmentWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->envPath = tempnam(sys_get_temp_dir(), 'evoriq_env_');
    file_put_contents($this->envPath, "APP_NAME=Evoriq\n");

    $this->sqlitePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evoriq_'.uniqid().'.sqlite';

    $this->writer = new EnvironmentWriter($this->envPath);
    $this->configurator = new DatabaseConfigurator($this->writer);
});

afterEach(function () {
    foreach ([$this->envPath, $this->sqlitePath] as $path) {
        if (isset($path) && is_file($path)) {
            @unlink($path);
        }
    }
});

function sqliteDto(string $database, bool $create = false): DatabaseConfigDTO
{
    return new DatabaseConfigDTO(
        driver: DatabaseDriver::SQLITE,
        host: '',
        port: 0,
        database: $database,
        username: '',
        password: '',
        createDatabase: $create,
    );
}

test('it exposes the supported database drivers', function () {
    $drivers = $this->configurator->drivers();

    expect(array_column($drivers, 'value'))
        ->toBe(['pgsql', 'mysql', 'mariadb', 'sqlite']);
});

test('it reads the current configuration from the environment file', function () {
    $this->writer->set(['DB_CONNECTION' => 'mysql', 'DB_HOST' => 'db.local']);

    $current = $this->configurator->current();

    expect($current['driver'])->toBe('mysql')
        ->and($current['host'])->toBe('db.local');
});

test('it probes an in-memory sqlite connection', function () {
    $result = $this->configurator->test(sqliteDto(':memory:'));

    expect($result['ok'])->toBeTrue();
});

test('it creates a missing sqlite database', function () {
    $result = $this->configurator->provision(sqliteDto($this->sqlitePath, create: true));

    expect($result['ok'])->toBeTrue()
        ->and($result['created'])->toBeTrue()
        ->and(is_file($this->sqlitePath))->toBeTrue();
});

test('it writes the connection to the environment file', function () {
    $this->configurator->write(sqliteDto($this->sqlitePath), reload: false);

    expect($this->writer->get('DB_CONNECTION'))->toBe('sqlite')
        ->and($this->writer->get('DB_DATABASE'))->toBe($this->sqlitePath)
        ->and(is_file($this->sqlitePath))->toBeTrue();
});
