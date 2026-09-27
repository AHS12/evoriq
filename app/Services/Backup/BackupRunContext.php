<?php

namespace App\Services\Backup;

use App\Models\BackupRun;

/**
 * Correlates the spatie backup events (fired inside the current process while
 * `backup:*` commands run via Artisan::call) with the BackupRun row that
 * started them. Events never fire on another process, so a static holder is
 * sufficient and avoids cache round-trips.
 */
final class BackupRunContext
{
    private static ?int $runId = null;

    public static function set(BackupRun $run): void
    {
        self::$runId = $run->id;
    }

    public static function currentId(): ?int
    {
        return self::$runId;
    }

    public static function current(): ?BackupRun
    {
        if (self::$runId === null) {
            return null;
        }

        /** @var BackupRun|null $run */
        $run = BackupRun::query()->find(self::$runId);

        return $run;
    }

    public static function forget(): void
    {
        self::$runId = null;
    }
}
