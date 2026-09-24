<?php

namespace App\Exports;

use App\Enums\DataEntity;
use App\Exports\Contracts\Exportable;
use Illuminate\Support\Collection;

/**
 * Generates the downloadable CSV/XLSX import template for an entity: the
 * heading row plus one example row.
 */
class ImportTemplateExport implements Exportable
{
    public function __construct(private readonly DataEntity $entity) {}

    /**
     * @return Collection<int, mixed>
     */
    public function collection(): Collection
    {
        return collect([$this->entity->importSampleRow()]);
    }

    public function total(): int
    {
        return 1;
    }

    public function stage(): string
    {
        return 'Generating template';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->entity->importHeadings();
    }
}
