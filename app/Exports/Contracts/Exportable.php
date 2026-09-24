<?php

namespace App\Exports\Contracts;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Contract every Evoriq exporter implements so jobs can resolve them by entity.
 *
 * @extends FromCollection<int, mixed>
 */
interface Exportable extends FromCollection, WithHeadings
{
    /**
     * Total number of rows this export will produce, for progress reporting.
     */
    public function total(): int;

    /**
     * A short label for the generation step shown while the job runs.
     */
    public function stage(): string;
}
