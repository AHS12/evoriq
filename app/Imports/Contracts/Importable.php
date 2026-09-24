<?php

namespace App\Imports\Contracts;

use App\Enums\DataEntity;
use App\Imports\ImportResult;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Contract every Evoriq importer implements. The job resolves one via
 * `DataEntity::makeImporter()` and hands it to maatwebsite/excel.
 */
interface Importable extends SkipsEmptyRows, ToCollection, WithChunkReading, WithHeadingRow
{
    public function entity(): DataEntity;

    /**
     * A snapshot of counters and row-level issues after the import.
     */
    public function result(): ImportResult;
}
