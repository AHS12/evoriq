<?php

namespace App\Exports;

use App\Exports\Contracts\Exportable;
use Illuminate\Support\Collection;

/**
 * Writes the skipped/failed rows of an import to a downloadable CSV report.
 */
class ImportReportExport implements Exportable
{
    /**
     * @param  array<int, array{row: int, type: string, message: string}>  $issues
     */
    public function __construct(private array $issues) {}

    /**
     * @return Collection<int, mixed>
     */
    public function collection(): Collection
    {
        return collect($this->issues)->map(static fn (array $issue): array => [
            $issue['row'],
            $issue['type'],
            $issue['message'],
        ]);
    }

    public function total(): int
    {
        return count($this->issues);
    }

    public function stage(): string
    {
        return 'Generating report';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Row', 'Type', 'Message'];
    }
}
