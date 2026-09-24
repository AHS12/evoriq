<?php

namespace App\Imports\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Best-effort data-row count for an import file, used for progress reporting.
 *
 * Returns 0 when the count cannot be determined (the UI then shows an
 * indeterminate progress bar).
 */
final class ImportCounter
{
    public static function count(?string $path, ?string $disk = 'local'): int
    {
        if ($path === null || $path === '') {
            return 0;
        }

        try {
            $storage = Storage::disk($disk ?? 'local');
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (in_array($extension, ['csv', 'txt'], true)) {
                return self::countCsv($storage, $path);
            }

            $absolutePath = $storage->path($path);
            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($absolutePath)->getActiveSheet();

            return max(0, $sheet->getHighestDataRow() - 1);
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countCsv(FilesystemAdapter $storage, string $path): int
    {
        $stream = $storage->readStream($path);

        if ($stream === null) {
            return 0;
        }

        $lines = 0;

        while (fgetcsv($stream) !== false) {
            $lines++;
        }

        fclose($stream);

        return max(0, $lines - 1);
    }
}
