<?php

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Setup\EnvironmentWriter;
use App\Services\Setup\SetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->envPath = tempnam(sys_get_temp_dir(), 'evoriq_env_');
    file_put_contents($this->envPath, "APP_NAME=Evoriq\n");

    $this->repository = Mockery::mock(UserRepositoryInterface::class);
    $this->writer = new EnvironmentWriter($this->envPath);
    $this->service = new SetupService($this->repository, $this->writer);
});

afterEach(function () {
    Mockery::close();

    if (isset($this->envPath) && is_file($this->envPath)) {
        @unlink($this->envPath);
    }
});

test('setup is incomplete without a lock, flag or super admin', function () {
    $this->repository->shouldReceive('hasSuperAdmin')->once()->andReturnFalse();

    expect($this->service->isComplete())->toBeFalse();
});

test('setup is complete when the lock file exists', function () {
    Storage::disk('local')->put(SetupService::LOCK_FILE, now()->toIso8601String());

    $this->repository->shouldNotReceive('hasSuperAdmin');

    expect($this->service->isComplete())->toBeTrue();
});

test('setup is complete when a super admin already exists', function () {
    $this->repository->shouldReceive('hasSuperAdmin')->once()->andReturnTrue();

    expect($this->service->isComplete())->toBeTrue();
});

test('setup is incomplete when a reset marker exists', function () {
    Storage::disk('local')->put(SetupService::RESET_MARKER, now()->toIso8601String());

    $this->repository->shouldNotReceive('hasSuperAdmin');

    expect($this->service->isComplete())->toBeFalse();
});

test('requirements report the environment checks in order', function () {
    $keys = array_column($this->service->requirements(), 'key');

    expect($keys)->toBe([
        'php',
        'extensions',
        'storage',
        'cache',
        'environment',
        'key',
        'database',
    ]);
});

test('the database requirement is informational', function () {
    $database = collect($this->service->requirements())
        ->firstWhere('key', 'database');

    expect($database['required'])->toBeFalse();
});

test('generate app key writes a key to the environment file', function () {
    $key = $this->service->generateAppKey();

    expect($key)->toStartWith('base64:')
        ->and($this->writer->get('APP_KEY'))->toBe($key)
        ->and(config('app.key'))->toBe($key);
});

test('status reports migrated and seeded state', function () {
    $status = $this->service->status();

    expect($status['database']['migrated'])->toBeTrue()
        ->and($status['database']['seeded'])->toBeTrue()
        ->and($status['environment']['env_exists'])->toBeTrue();
});
