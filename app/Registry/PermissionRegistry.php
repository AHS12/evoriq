<?php

namespace App\Registry;

final class PermissionRegistry
{
    /**
     * Get the formatted permissions declared for a module.
     *
     * @param  array<int, string>  $exclude
     * @return array<int, array<string, mixed>>
     */
    public static function forModule(string $module, array $exclude = []): array
    {
        /** @var array<int, array<string, mixed>> $declared */
        $declared = (array) config("permission-registry.{$module}.permissions", []);

        $permissions = array_map(self::format(...), $declared);

        if ($exclude !== []) {
            $permissions = array_filter(
                $permissions,
                static fn (array $permission): bool => ! in_array($permission['name'], $exclude, true),
            );
        }

        return array_values($permissions);
    }

    /**
     * Get every permission declared across all modules.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAllModulePermissions(): array
    {
        $permissions = [];

        foreach (self::modules() as $module) {
            $permissions = [...$permissions, ...self::forModule($module)];
        }

        return $permissions;
    }

    /**
     * Get the names of every registered module.
     *
     * @return array<int, string>
     */
    public static function modules(): array
    {
        return array_keys((array) config('permission-registry', []));
    }

    /**
     * Normalize a declared permission for database insertion.
     *
     * @param  array<string, mixed>  $permission
     * @return array<string, mixed>
     */
    public static function format(array $permission): array
    {
        return [
            'name' => $permission['name'],
            'guard_name' => config('auth.defaults.guard'),
            'group' => $permission['group'] ?? null,
            'description' => $permission['description'] ?? null,
            'is_system' => $permission['is_system'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
