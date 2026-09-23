<?php

namespace App\Exports;

use App\Exports\Contracts\Exportable;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<User>
 */
class UserExport implements Exportable, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(private array $filters = []) {}

    /**
     * @return Collection<int, User>
     */
    public function collection(): Collection
    {
        $query = User::query()->with('roles');

        $search = $this->filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('id')->get();
    }

    /**
     * @param  User  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->email,
            $row->email_verified_at?->format('Y-m-d H:i:s') ?? 'Unverified',
            $row->roles->pluck('name')->implode(', '),
            $row->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['ID', 'Name', 'Email', 'Verified At', 'Roles', 'Created At'];
    }
}
