<?php

namespace App\Exports;

use App\Exports\Contracts\Exportable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<User>
 */
class UserExport implements Exportable, WithMapping
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(private array $parameters = []) {}

    /**
     * @return Collection<int, User>
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

    /**
     * The filtered query shared by `collection()` and `total()`.
     *
     * @return Builder<User>
     */
    private function query(): Builder
    {
        $query = User::query()->with('roles');

        $search = $this->parameters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $status = $this->parameters['status'] ?? null;

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $role = $this->parameters['role'] ?? null;

        if (is_string($role) && $role !== '') {
            $query->role($role);
        }

        return $query;
    }
}
