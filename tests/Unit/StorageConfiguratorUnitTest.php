<?php

use App\DTOs\Storage\StorageConfigDTO;
use App\Services\Setup\EnvironmentWriter;
use App\Services\Storage\StorageConfigurator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function tempEnvPath(): string
{
    return sys_get_temp_dir().'/storage-configurator-test-'.uniqid().'.env';
}

function configuratorOn(string $path): StorageConfigurator
{
    return new StorageConfigurator(new EnvironmentWriter($path));
}

function readEnv(string $path): array
{
    return (new EnvironmentWriter($path))->read();
}

function storageConfigDto(array $overrides = []): StorageConfigDTO
{
    return new StorageConfigDTO(...array_merge([
        'enabled' => true,
        'provider' => 'r2',
        'accessKeyId' => 'test-key',
        'secretAccessKey' => 'test-secret',
        'region' => 'auto',
        'bucket' => 'my-bucket',
        'endpoint' => 'https://account.r2.cloudflarestorage.com',
        'usePathStyle' => true,
    ], $overrides));
}

test('write persists the AWS keys to the environment file', function () {
    $path = tempEnvPath();

    configuratorOn($path)->write(storageConfigDto());

    $values = readEnv($path);

    expect($values['AWS_ENABLED'])->toBe('true')
        ->and($values['AWS_ACCESS_KEY_ID'])->toBe('test-key')
        ->and($values['AWS_SECRET_ACCESS_KEY'])->toBe('test-secret')
        ->and($values['AWS_DEFAULT_REGION'])->toBe('auto')
        ->and($values['AWS_BUCKET'])->toBe('my-bucket')
        ->and($values['AWS_USE_PATH_STYLE_ENDPOINT'])->toBe('true');
});

test('write keeps the existing secret when a blank one is submitted', function () {
    $path = tempEnvPath();
    $configurator = configuratorOn($path);

    $configurator->write(storageConfigDto());
    $configurator->write(storageConfigDto(['secretAccessKey' => null, 'accessKeyId' => 'rotated-key']));

    $values = readEnv($path);

    expect($values['AWS_SECRET_ACCESS_KEY'])->toBe('test-secret')
        ->and($values['AWS_ACCESS_KEY_ID'])->toBe('rotated-key');
});

test('current never exposes the secret', function () {
    $configurator = configuratorOn(tempEnvPath());

    $configurator->write(storageConfigDto());

    $current = $configurator->current();

    expect($current)->not->toHaveKey('secret_access_key')
        ->and($current['has_secret'])->toBeTrue()
        ->and($current['provider'])->toBe('r2')
        ->and($current['enabled'])->toBeTrue();
});

test('the provider is detected from the endpoint', function () {
    $configurator = configuratorOn(tempEnvPath());

    $configurator->write(storageConfigDto(['provider' => 's3', 'endpoint' => null, 'region' => 'us-east-1', 'usePathStyle' => false]));

    expect($configurator->current()['provider'])->toBe('s3');

    $configurator->write(storageConfigDto(['provider' => 'custom', 'endpoint' => 'https://storage.example.com']));

    expect($configurator->current()['provider'])->toBe('custom');
});

test('isConfigured requires the secret and bucket to be present', function () {
    $configurator = configuratorOn(tempEnvPath());

    expect($configurator->isConfigured())->toBeFalse();

    $configurator->write(storageConfigDto());

    expect($configurator->isConfigured())->toBeTrue();
});

test('the probe reports success against a fake remote disk', function () {
    Storage::fake('s3');

    expect(configuratorOn(tempEnvPath())->test())->toMatchArray([
        'ok' => true,
    ]);
});

test('the probe reports failure when the remote disk rejects the write', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->andThrow(new RuntimeException('access denied'));

    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

    $result = configuratorOn(tempEnvPath())->test();

    expect($result['ok'])->toBeFalse()
        ->and($result['message'])->toContain('access denied');
});
