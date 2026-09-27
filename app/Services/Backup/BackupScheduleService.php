<?php

namespace App\Services\Backup;

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\BackupRunType;
use App\Enums\SettingKey;
use App\Repositories\Contracts\BackupRunRepositoryInterface;
use App\Services\Storage\StorageConfigurator;
use Illuminate\Support\Carbon;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Throwable;

/**
 * Reads the backup settings (schedule, destination, retention) and resolves
 * them against the spatie/laravel-backup configuration at runtime, so changes
 * made in the settings UI take effect on the next scheduler tick without any
 * code or scheduler restart.
 */
class BackupScheduleService
{
    /**
     * The local disk every backup is written to.
     */
    public const LOCAL_DISK = 'backups';

    public function __construct(
        protected BackupRunRepositoryInterface $runs,
        protected StorageConfigurator $storage,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->value(SettingKey::BACKUP_ENABLED);
    }

    public function frequency(): string
    {
        return (string) $this->value(SettingKey::BACKUP_FREQUENCY);
    }

    public function time(): string
    {
        return (string) $this->value(SettingKey::BACKUP_TIME);
    }

    public function keepDays(): int
    {
        return max(1, (int) $this->value(SettingKey::BACKUP_KEEP_DAYS));
    }

    public function sendToRemote(): bool
    {
        return (bool) $this->value(SettingKey::BACKUP_SEND_TO_REMOTE);
    }

    public function emailEnabled(): bool
    {
        return (bool) $this->value(SettingKey::BACKUP_EMAIL_ENABLED);
    }

    /**
     * The stored archive email recipients (comma-separated), or an empty
     * string when unset.
     */
    public function emailRecipients(): string
    {
        return trim((string) $this->value(SettingKey::BACKUP_EMAIL_RECIPIENTS));
    }

    /**
     * The destination disks for a backup run: the local disk always, plus the
     * remote disk when a mirror copy is requested and remote storage is
     * actually configured.
     *
     * @return list<string>
     */
    public function destinationDisks(): array
    {
        $disks = [self::LOCAL_DISK];

        if ($this->sendToRemote() && $this->storage->isConfigured()) {
            $disks[] = 's3';
        }

        return $disks;
    }

    /**
     * Whether the remote disk is part of the current destinations.
     */
    public function usesRemote(): bool
    {
        return in_array('s3', $this->destinationDisks(), true);
    }

    /**
     * Whether remote object storage has usable credentials configured.
     */
    public function remoteConfigured(): bool
    {
        return $this->storage->isConfigured();
    }

    /**
     * The bytes currently used by backups on the local destination disk.
     */
    public function localDiskUsageMb(): ?float
    {
        try {
            $destination = BackupDestination::create(self::LOCAL_DISK, (string) config('backup.backup.name'));

            if (! $destination->isReachable()) {
                return null;
            }

            return round($destination->usedStorage() / 1024 / 1024, 2);
        } catch (Throwable) {
            return null;
        }
    }

    public function maxStorageMb(): int
    {
        return max(1, (int) $this->value(SettingKey::BACKUP_MAX_STORAGE_MB));
    }

    public function maxAgeDays(): int
    {
        return max(1, (int) $this->value(SettingKey::BACKUP_MAX_AGE_DAYS));
    }

    public function notifyOnFailure(): bool
    {
        return (bool) $this->value(SettingKey::BACKUP_NOTIFY_ON_FAILURE);
    }

    /**
     * Whether a scheduled backup is due at the given moment: the configured
     * slot has passed and no backup was attempted since that slot started.
     */
    public function isBackupDue(?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now();

        if (! $this->isEnabled() || $this->runs->isActive(BackupRunType::BACKUP)) {
            return false;
        }

        $slot = $this->dueSlot($now);

        if ($now->lessThan($slot)) {
            return false;
        }

        $lastAttempt = $this->runs->latestOfType(BackupRunType::BACKUP);

        return $lastAttempt === null || $lastAttempt->created_at->lessThan($slot);
    }

    /**
     * The next moment a scheduled backup will run (null when disabled).
     */
    public function nextRunAt(?Carbon $now = null): ?Carbon
    {
        $now = $now ?? Carbon::now();

        if (! $this->isEnabled()) {
            return null;
        }

        if ($this->frequency() === 'weekly') {
            $slot = $now->copy()->setTimeFromTimeString($this->time());

            if ($now->isSunday() && $now->lessThan($slot)) {
                return $slot;
            }

            return $now->copy()->next(Carbon::SUNDAY)->setTimeFromTimeString($this->time());
        }

        $slot = $now->copy()->setTimeFromTimeString($this->time());

        return $slot->greaterThan($now) ? $slot : $slot->addDay();
    }

    /**
     * The spatie commands are constructed at console-kernel boot with a
     * container-scoped Config, so runtime `config()` overrides never reach
     * them. Instead, build the full config array with the settings applied
     * and pass it to the commands via their `--config=` option.
     *
     * @return array<string, mixed>
     */
    public function runtimeConfig(): array
    {
        $config = config('backup');

        assert(is_array($config));

        $config['backup']['destination']['disks'] = $this->destinationDisks();

        $config['cleanup']['default_strategy']['keep_all_backups_for_days'] = $this->keepDays();
        $config['cleanup']['default_strategy']['delete_oldest_backups_when_using_more_megabytes_than'] = $this->maxStorageMb();

        if (isset($config['monitor_backups'][0]) && is_array($config['monitor_backups'][0])) {
            $config['monitor_backups'][0]['disks'] = $this->destinationDisks();
            $config['monitor_backups'][0]['health_checks'] ??= [];
            $config['monitor_backups'][0]['health_checks'][MaximumAgeInDays::class] = $this->maxAgeDays();
            $config['monitor_backups'][0]['health_checks'][MaximumStorageInMegabytes::class] = $this->maxStorageMb();
        }

        return $config;
    }

    /**
     * The most recent scheduled slot for the current frequency (today at the
     * configured time, or the current week's Sunday for weekly backups).
     */
    protected function dueSlot(Carbon $now): Carbon
    {
        if ($this->frequency() === 'weekly') {
            $slot = $now->isSunday()
                ? $now->copy()
                : $now->copy()->previous(Carbon::SUNDAY);

            return $slot->setTimeFromTimeString($this->time());
        }

        return $now->copy()->setTimeFromTimeString($this->time());
    }

    protected function value(SettingKey $key): mixed
    {
        return Settings::get($key->value, $key->defaultValue());
    }
}
