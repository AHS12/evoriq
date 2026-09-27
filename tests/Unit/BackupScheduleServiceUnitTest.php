<?php

use Ahs12\Setanjo\Facades\Settings;
use App\DTOs\Storage\StorageConfigDTO;
use App\Enums\BackupRunStatus;
use App\Enums\BackupRunType;
use App\Enums\SettingKey;
use App\Models\BackupRun;
use App\Repositories\Backup\BackupRunRepository;
use App\Repositories\Contracts\BackupRunRepositoryInterface;
use App\Services\Backup\BackupScheduleService;
use App\Services\Setup\EnvironmentWriter;
use App\Services\Storage\StorageConfigurator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(BackupRunRepositoryInterface::class);
    $this->service = new BackupScheduleService($this->repository, configuratorOnUnconfiguredDisk());

    Settings::set(SettingKey::BACKUP_ENABLED->value, true);
    Settings::set(SettingKey::BACKUP_FREQUENCY->value, 'daily');
    Settings::set(SettingKey::BACKUP_TIME->value, '01:30');
    Settings::set(SettingKey::BACKUP_SEND_TO_REMOTE->value, false);
    Settings::set(SettingKey::BACKUP_KEEP_DAYS->value, 14);
    Settings::set(SettingKey::BACKUP_MAX_STORAGE_MB->value, 5000);
    Settings::set(SettingKey::BACKUP_MAX_AGE_DAYS->value, 1);
});

/**
 * A storage configurator on a temp env file with no remote credentials.
 */
function configuratorOnUnconfiguredDisk(): StorageConfigurator
{
    return new StorageConfigurator(new EnvironmentWriter(
        sys_get_temp_dir().'/backup-schedule-test-'.uniqid().'.env',
    ));
}

/**
 * A run with an explicit creation moment (created_at is not fillable).
 */
function makeRunCreatedAt(string $timestamp): BackupRun
{
    return BackupRun::factory()->create()->forceFill(['created_at' => Carbon::parse($timestamp)]);
}

test('a backup is due when the configured slot has passed and nothing ran yet', function () {
    $this->repository
        ->shouldReceive('isActive')
        ->with(BackupRunType::BACKUP)
        ->once()
        ->andReturn(false);

    $this->repository
        ->shouldReceive('latestOfType')
        ->with(BackupRunType::BACKUP)
        ->once()
        ->andReturnNull();

    expect($this->service->isBackupDue(Carbon::parse('2026-09-27 06:00:00')))->toBeTrue();
});

test('a backup is not due when one already ran after the slot', function () {
    $this->repository
        ->shouldReceive('isActive')
        ->andReturnFalse();

    $this->repository
        ->shouldReceive('latestOfType')
        ->andReturn(makeRunCreatedAt('2026-09-27 01:31:00'));

    expect($this->service->isBackupDue(Carbon::parse('2026-09-27 06:00:00')))->toBeFalse();
});

test('a backup is due again on the next day even after a successful run', function () {
    $this->repository
        ->shouldReceive('isActive')
        ->andReturnFalse();

    $this->repository
        ->shouldReceive('latestOfType')
        ->andReturn(makeRunCreatedAt('2026-09-27 01:31:00'));

    expect($this->service->isBackupDue(Carbon::parse('2026-09-28 06:00:00')))->toBeTrue();
});

test('a backup is not due before the configured time', function () {
    $this->repository
        ->shouldReceive('isActive')
        ->andReturnFalse();

    $this->repository
        ->shouldReceive('latestOfType')
        ->andReturnNull();

    expect($this->service->isBackupDue(Carbon::parse('2026-09-27 00:00:00')))->toBeFalse();
});

test('a backup is not due when backups are disabled', function () {
    Settings::set(SettingKey::BACKUP_ENABLED->value, false);

    expect($this->service->isBackupDue(Carbon::parse('2026-09-27 06:00:00')))->toBeFalse();
});

test('a backup is not due while another run is active', function () {
    $this->repository
        ->shouldReceive('isActive')
        ->with(BackupRunType::BACKUP)
        ->andReturnTrue();

    expect($this->service->isBackupDue(Carbon::parse('2026-09-27 06:00:00')))->toBeFalse();
});

test('weekly backups are due after their weekly slot has passed', function () {
    Settings::set(SettingKey::BACKUP_FREQUENCY->value, 'weekly');

    // 2026-09-27 is a Sunday; the slot is that Sunday at 01:30.
    $this->repository
        ->shouldReceive('isActive')
        ->andReturnFalse();

    $this->repository
        ->shouldReceive('latestOfType')
        ->andReturnNull();

    // Monday: the Sunday slot has passed.
    expect($this->service->isBackupDue(Carbon::parse('2026-09-28 06:00:00')))->toBeTrue();
});

test('nextRunAt resolves the next daily slot', function () {
    expect($this->service->nextRunAt(Carbon::parse('2026-09-27 06:00:00'))?->format('Y-m-d H:i'))
        ->toBe('2026-09-28 01:30');

    expect($this->service->nextRunAt(Carbon::parse('2026-09-27 00:00:00'))?->format('Y-m-d H:i'))
        ->toBe('2026-09-27 01:30');

    expect($this->service->nextRunAt(Carbon::parse('2026-09-27 06:00:00')))
        ->not->toBeNull();
});

test('nextRunAt resolves the next weekly slot', function () {
    Settings::set(SettingKey::BACKUP_FREQUENCY->value, 'weekly');

    // 2026-09-27 is a Sunday, before 01:30 → same day slot.
    expect($this->service->nextRunAt(Carbon::parse('2026-09-27 00:00:00'))?->format('Y-m-d H:i'))
        ->toBe('2026-09-27 01:30');

    // After the Sunday slot → next Sunday (2026-10-04).
    expect($this->service->nextRunAt(Carbon::parse('2026-09-27 06:00:00'))?->format('Y-m-d H:i'))
        ->toBe('2026-10-04 01:30');
});

test('nextRunAt is null when backups are disabled', function () {
    Settings::set(SettingKey::BACKUP_ENABLED->value, false);

    expect($this->service->nextRunAt(Carbon::parse('2026-09-27 06:00:00')))->toBeNull();
});

test('runtimeConfig applies the stored settings to the spatie config array', function () {
    Settings::set(SettingKey::BACKUP_KEEP_DAYS->value, 30);
    Settings::set(SettingKey::BACKUP_MAX_STORAGE_MB->value, 2048);
    Settings::set(SettingKey::BACKUP_MAX_AGE_DAYS->value, 3);

    $config = $this->service->runtimeConfig();

    expect($config['backup']['destination']['disks'])->toBe(['backups'])
        ->and($config['cleanup']['default_strategy']['keep_all_backups_for_days'])->toBe(30)
        ->and($config['cleanup']['default_strategy']['delete_oldest_backups_when_using_more_megabytes_than'])->toBe(2048)
        ->and($config['monitor_backups'][0]['disks'])->toBe(['backups'])
        ->and($config['monitor_backups'][0]['health_checks'][MaximumAgeInDays::class])->toBe(3)
        ->and($config['monitor_backups'][0]['health_checks'][MaximumStorageInMegabytes::class])->toBe(2048);
});

test('the local disk is always a destination and remote only when configured', function () {
    expect($this->service->destinationDisks())->toBe(['backups'])
        ->and($this->service->usesRemote())->toBeFalse();

    // Remote enabled but no credentials -> still local only.
    Settings::set(SettingKey::BACKUP_SEND_TO_REMOTE->value, true);

    expect($this->service->destinationDisks())->toBe(['backups']);

    // With credentials the remote disk joins the destinations.
    $path = sys_get_temp_dir().'/backup-schedule-test-'.uniqid().'.env';
    $configurator = new StorageConfigurator(new EnvironmentWriter($path));
    $configurator->write(new StorageConfigDTO(
        enabled: true,
        provider: 'r2',
        accessKeyId: 'key',
        secretAccessKey: 'secret',
        region: 'auto',
        bucket: 'bucket',
        endpoint: 'https://account.r2.cloudflarestorage.com',
        usePathStyle: true,
    ));

    $service = new BackupScheduleService($this->repository, $configurator);

    expect($service->destinationDisks())->toBe(['backups', 's3'])
        ->and($service->usesRemote())->toBeTrue();
});

test('the repository reports completed runs since a moment', function () {
    $repository = new BackupRunRepository;

    expect($repository->hasCompletedSince(BackupRunType::CLEANUP, Carbon::today()))->toBeFalse();

    BackupRun::factory()->ofType(BackupRunType::CLEANUP)->completed()->create();

    expect($repository->hasCompletedSince(BackupRunType::CLEANUP, Carbon::today()))->toBeTrue();
});

test('the repository detects active runs', function () {
    $repository = new BackupRunRepository;

    BackupRun::factory()->running()->create();

    expect($repository->isActive(BackupRunType::BACKUP))->toBeTrue()
        ->and($repository->isActive(BackupRunType::MONITOR))->toBeFalse();

    BackupRun::factory()->ofType(BackupRunType::BACKUP)->create(['status' => BackupRunStatus::FAILED]);

    expect($repository->isActive(BackupRunType::BACKUP))->toBeTrue();
});
