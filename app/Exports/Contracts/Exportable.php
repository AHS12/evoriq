<?php

namespace App\Exports\Contracts;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Contract every Evoriq exporter implements so jobs can resolve them by entity.
 *
 * @extends FromCollection<int, mixed>
 */
interface Exportable extends FromCollection, WithHeadings {}
