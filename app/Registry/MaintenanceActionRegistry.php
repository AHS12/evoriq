<?php

namespace App\Registry;

use App\Enums\QueueName;

final class MaintenanceActionRegistry
{
    /**
     * The maintenance actions exposed in the UI.
     *
     * @var array<string, array{command: string, label: string, description: string, queue: QueueName}>
     */
    private const ACTIONS = [
        'cache-clear' => [
            'command' => 'cache:clear',
            'label' => 'Clear application cache',
            'description' => 'Flush the application cache store.',
            'queue' => QueueName::DEFAULT,
        ],
        'config-clear' => [
            'command' => 'config:clear',
            'label' => 'Clear config cache',
            'description' => 'Remove the cached configuration file.',
            'queue' => QueueName::DEFAULT,
        ],
        'route-clear' => [
            'command' => 'route:clear',
            'label' => 'Clear route cache',
            'description' => 'Remove the cached routes file.',
            'queue' => QueueName::DEFAULT,
        ],
        'view-clear' => [
            'command' => 'view:clear',
            'label' => 'Clear compiled views',
            'description' => 'Remove compiled Blade templates.',
            'queue' => QueueName::DEFAULT,
        ],
        'settings-sync' => [
            'command' => 'settings:sync',
            'label' => 'Sync settings',
            'description' => 'Re-apply the default settings.',
            'queue' => QueueName::DEFAULT,
        ],
        'permission-sync' => [
            'command' => 'permission:sync',
            'label' => 'Sync permissions',
            'description' => 'Re-sync permissions from the registry.',
            'queue' => QueueName::DEFAULT,
        ],
        'setup-reset' => [
            'command' => 'setup:reset',
            'label' => 'Re-open setup wizard',
            'description' => 'Re-run the first-run onboarding. Keeps the existing super admin account.',
            'queue' => QueueName::HEAVY,
        ],
    ];

    /**
     * Whether the given action key is registered.
     */
    public static function has(string $key): bool
    {
        return isset(self::ACTIONS[$key]);
    }

    /**
     * Resolve a single action definition.
     *
     * @return array{command: string, label: string, description: string, queue: QueueName}|null
     */
    public static function get(string $key): ?array
    {
        return self::ACTIONS[$key] ?? null;
    }

    /**
     * The actions formatted for the UI.
     *
     * @return array<int, array{key: string, label: string, description: string}>
     */
    public static function actions(): array
    {
        $actions = [];

        foreach (self::ACTIONS as $key => $action) {
            $actions[] = [
                'key' => $key,
                'label' => $action['label'],
                'description' => $action['description'],
            ];
        }

        return $actions;
    }

    /**
     * Every registered action key.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::ACTIONS);
    }
}
