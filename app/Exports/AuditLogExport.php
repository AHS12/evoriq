<?php

namespace App\Exports;

use App\Exports\Contracts\Exportable;
use App\Models\AuditActivity;
use App\Models\DataProcessingJob;
use App\Models\Role;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<AuditActivity>
 */
class AuditLogExport implements Exportable, WithMapping
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(private array $parameters = []) {}

    /**
     * @return Collection<int, AuditActivity>
     */
    public function collection(): Collection
    {
        return $this->query()->orderBy('id')->get();
    }

    public function total(): int
    {
        return $this->query()->count();
    }

    public function stage(): string
    {
        return 'Generating file';
    }

    /**
     * @param  AuditActivity  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->created_at?->format('Y-m-d H:i:s'),
            $row->causer instanceof User ? $row->causer->name : 'System',
            $row->causer instanceof User ? $row->causer->email : '',
            $row->log_name,
            $row->event,
            $row->description,
            $this->subjectLabel($row),
            $row->getProperty('ip_address'),
            $row->getProperty('correlation_id'),
            $row->attribute_changes !== null ? json_encode($row->attribute_changes->toArray()) : '',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'ID', 'When', 'User', 'User Email', 'Channel', 'Event',
            'Description', 'Subject', 'IP Address', 'Correlation ID', 'Changes',
        ];
    }

    /**
     * The filtered query shared by `collection()` and `total()`. Filters
     * mirror the audit page (AuditLogFilterDTO) so an export matches what
     * the admin sees. For very large trails, prefer filtering.
     *
     * @return Builder<AuditActivity>
     */
    private function query(): Builder
    {
        $query = AuditActivity::query()->with(['causer', 'subject']);

        $search = $this->parameters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where('description', 'like', "%{$search}%");
        }

        $channel = $this->parameters['channel'] ?? null;

        if (is_string($channel) && $channel !== '') {
            $query->where('log_name', $channel);
        }

        $event = $this->parameters['event'] ?? null;

        if (is_string($event) && $event !== '') {
            $query->where('event', $event);
        }

        $causerId = $this->parameters['causer_id'] ?? null;

        if (is_numeric($causerId)) {
            $query->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', (int) $causerId);
        }

        $dateFrom = $this->parameters['date_from'] ?? null;

        if (is_string($dateFrom) && $dateFrom !== '') {
            $query->where('created_at', '>=', $dateFrom);
        }

        $dateTo = $this->parameters['date_to'] ?? null;

        if (is_string($dateTo) && $dateTo !== '') {
            $query->where('created_at', '<=', $dateTo);
        }

        return $query;
    }

    private function subjectLabel(AuditActivity $row): string
    {
        $subject = $row->subject;

        if ($subject === null) {
            return '';
        }

        if ($subject instanceof User) {
            return $subject->email;
        }

        if ($subject instanceof Role) {
            return $subject->name;
        }

        if ($subject instanceof Upload) {
            return $subject->name ?? (string) $subject->file_name;
        }

        if ($subject instanceof DataProcessingJob) {
            return $subject->displayName();
        }

        return (string) $subject->getKey();
    }
}
