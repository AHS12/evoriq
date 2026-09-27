<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganizationSeeder extends Seeder
{
    /**
     * The application tables that carry an organization_id.
     *
     * @var array<int, string>
     */
    protected array $tables = [
        'users',
        'data_processing_jobs',
        'notifications',
        'uploads',
    ];

    /**
     * Seed the default organization and backfill existing rows.
     */
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default', 'is_default' => true],
        );

        if (! $organization->is_default) {
            $organization->update(['is_default' => true]);
        }

        Organization::forgetDefault();

        // Keep pre-existing rows visible under the organization global scope.
        // This is a cheap backfill of small application tables; the wide
        // Clockify tables are born with the column instead (ORG-01).
        foreach ($this->tables as $table) {
            DB::table($table)
                ->whereNull('organization_id')
                ->update(['organization_id' => $organization->getKey()]);
        }
    }
}
