<?php

/*
|--------------------------------------------------------------------------
| Permission Registry
|--------------------------------------------------------------------------
|
| Every module declares its permissions here. `App\Registry\PermissionRegistry`
| reads this config to seed permissions and to sync new ones via
| `php artisan permission:sync`.
|
| Each permission supports:
|   name         (required) unique permission name, `{module}.{action}`
|   group        (required) grouping key, used in the UI
|   description  (required) human readable label
|   is_system    (optional) system-level permission, not assignable to custom roles
|
*/

return [

    'user' => [
        'permissions' => [
            ['name' => 'user.view', 'group' => 'user', 'description' => 'View user details'],
            ['name' => 'user.view.all', 'group' => 'user', 'description' => 'View all users'],
            ['name' => 'user.create', 'group' => 'user', 'description' => 'Create users'],
            ['name' => 'user.update', 'group' => 'user', 'description' => 'Update users'],
            ['name' => 'user.delete', 'group' => 'user', 'description' => 'Delete users'],
            ['name' => 'user.export', 'group' => 'user', 'description' => 'Export users'],
            ['name' => 'user.import', 'group' => 'user', 'description' => 'Import users'],
        ],
    ],

    'role' => [
        'permissions' => [
            ['name' => 'role.view', 'group' => 'role', 'description' => 'View role details', 'is_system' => true],
            ['name' => 'role.view.all', 'group' => 'role', 'description' => 'View all roles', 'is_system' => true],
            ['name' => 'role.create', 'group' => 'role', 'description' => 'Create roles', 'is_system' => true],
            ['name' => 'role.update', 'group' => 'role', 'description' => 'Update roles', 'is_system' => true],
            ['name' => 'role.delete', 'group' => 'role', 'description' => 'Delete roles', 'is_system' => true],
        ],
    ],

    'connection' => [
        'permissions' => [
            ['name' => 'connection.view', 'group' => 'connection', 'description' => 'View Clockify connections'],
            ['name' => 'connection.view.all', 'group' => 'connection', 'description' => 'View all connections'],
            ['name' => 'connection.create', 'group' => 'connection', 'description' => 'Create connections'],
            ['name' => 'connection.update', 'group' => 'connection', 'description' => 'Update connections'],
            ['name' => 'connection.delete', 'group' => 'connection', 'description' => 'Delete connections'],
            ['name' => 'connection.credentials.update', 'group' => 'connection', 'description' => 'Update connection credentials'],
        ],
    ],

    'workspace' => [
        'permissions' => [
            ['name' => 'workspace.view', 'group' => 'workspace', 'description' => 'View workspaces'],
            ['name' => 'workspace.view.all', 'group' => 'workspace', 'description' => 'View all workspaces'],
            ['name' => 'workspace.create', 'group' => 'workspace', 'description' => 'Create workspaces'],
            ['name' => 'workspace.update', 'group' => 'workspace', 'description' => 'Update workspaces'],
            ['name' => 'workspace.delete', 'group' => 'workspace', 'description' => 'Delete workspaces'],
        ],
    ],

    'sync' => [
        'permissions' => [
            ['name' => 'sync.view', 'group' => 'sync', 'description' => 'View sync runs'],
            ['name' => 'sync.view.all', 'group' => 'sync', 'description' => 'View all sync runs'],
            ['name' => 'sync.trigger', 'group' => 'sync', 'description' => 'Trigger a synchronization'],
            ['name' => 'sync.reconcile', 'group' => 'sync', 'description' => 'Run a reconciliation sync'],
            ['name' => 'sync.delete', 'group' => 'sync', 'description' => 'Cancel or delete sync runs'],
        ],
    ],

    'report' => [
        'permissions' => [
            ['name' => 'report.view', 'group' => 'report', 'description' => 'View reports'],
            ['name' => 'report.view.all', 'group' => 'report', 'description' => 'View all reports'],
            ['name' => 'report.create', 'group' => 'report', 'description' => 'Create reports'],
            ['name' => 'report.update', 'group' => 'report', 'description' => 'Update reports'],
            ['name' => 'report.delete', 'group' => 'report', 'description' => 'Delete reports'],
            ['name' => 'report.export', 'group' => 'report', 'description' => 'Export reports'],
        ],
    ],

    'export' => [
        'permissions' => [
            ['name' => 'export.create', 'group' => 'export', 'description' => 'Export any entity (global)'],
        ],
    ],

    'import' => [
        'permissions' => [
            ['name' => 'import.create', 'group' => 'import', 'description' => 'Import any entity (global)'],
        ],
    ],

    'data-processing' => [
        'permissions' => [
            ['name' => 'data-processing.view', 'group' => 'data-processing', 'description' => 'Open the Data Processing Center and view own jobs'],
            ['name' => 'data-processing.view.all', 'group' => 'data-processing', 'description' => 'View every user\'s processing jobs'],
            ['name' => 'data-processing.manage', 'group' => 'data-processing', 'description' => 'Cancel, retry or duplicate any processing job'],
            ['name' => 'data-processing.delete', 'group' => 'data-processing', 'description' => 'Delete any processing job and its files'],
        ],
    ],

    'file' => [
        'permissions' => [
            ['name' => 'file.view', 'group' => 'file', 'description' => 'View files'],
            ['name' => 'file.view.all', 'group' => 'file', 'description' => 'View all files'],
            ['name' => 'file.create', 'group' => 'file', 'description' => 'Upload files'],
            ['name' => 'file.delete', 'group' => 'file', 'description' => 'Delete files'],
        ],
    ],

    'settings' => [
        'permissions' => [
            ['name' => 'settings.view', 'group' => 'settings', 'description' => 'View settings', 'is_system' => true],
            ['name' => 'settings.update', 'group' => 'settings', 'description' => 'Update settings', 'is_system' => true],
        ],
    ],

    'developer' => [
        'permissions' => [
            ['name' => 'developer.view', 'group' => 'developer', 'description' => 'Access developer tools', 'is_system' => true],
        ],
    ],

];
