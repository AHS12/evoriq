<?php

use App\Services\Setup\EnvironmentWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'evoriq_env_');
    file_put_contents(
        $this->path,
        "APP_NAME=Evoriq\n# keep me\nDB_CONNECTION=pgsql\n",
    );

    $this->writer = new EnvironmentWriter($this->path);
});

afterEach(function () {
    if (isset($this->path) && is_file($this->path)) {
        @unlink($this->path);
    }
});

test('it reads key value pairs and ignores comments', function () {
    expect($this->writer->read())
        ->toMatchArray(['APP_NAME' => 'Evoriq', 'DB_CONNECTION' => 'pgsql'])
        ->and($this->writer->get('APP_NAME'))->toBe('Evoriq');
});

test('it updates existing keys in place and preserves comments', function () {
    $this->writer->set(['DB_CONNECTION' => 'mysql']);

    $contents = file_get_contents($this->path);

    expect($contents)->toContain('# keep me')
        ->and($contents)->toContain('DB_CONNECTION=mysql')
        ->and($this->writer->get('DB_CONNECTION'))->toBe('mysql');
});

test('it appends missing keys', function () {
    $this->writer->set(['APP_KEY' => 'base64:abc']);

    expect($this->writer->get('APP_KEY'))->toBe('base64:abc')
        ->and(file_get_contents($this->path))->toContain('APP_KEY=base64:abc');
});

test('it quotes values that contain spaces', function () {
    $this->writer->set(['APP_NAME' => 'My App']);

    expect(file_get_contents($this->path))->toContain("APP_NAME='My App'")
        ->and($this->writer->get('APP_NAME'))->toBe('My App');
});

test('it writes null as an empty value', function () {
    $this->writer->set(['REDIS_PASSWORD' => null]);

    expect(file_get_contents($this->path))->toContain('REDIS_PASSWORD=')
        ->and($this->writer->get('REDIS_PASSWORD'))->toBe('');
});

test('it seeds a missing environment file from the example', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'evoriq_env_'.uniqid();
    mkdir($directory);

    file_put_contents($directory.DIRECTORY_SEPARATOR.'.env.example', "APP_NAME=Evoriq\n");

    $writer = new EnvironmentWriter($directory.DIRECTORY_SEPARATOR.'.env');

    expect($writer->exists())->toBeFalse()
        ->and($writer->ensureExists())->toBeTrue()
        ->and($writer->exists())->toBeTrue()
        ->and($writer->get('APP_NAME'))->toBe('Evoriq');

    @unlink($directory.DIRECTORY_SEPARATOR.'.env');
    @unlink($directory.DIRECTORY_SEPARATOR.'.env.example');
    @rmdir($directory);
});
