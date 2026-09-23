export type DeveloperTool = {
    key: string;
    title: string;
    description: string;
    href: string;
    available: boolean;
};

export type HealthCheck = {
    name: string;
    status: string;
    summary: string;
};

export type SystemInfo = {
    environment: string;
    php_version: string;
    laravel_version: string;
    timezone: string;
    debug: boolean;
    config_cached: boolean;
    routes_cached: boolean;
    events_cached: boolean;
};

export type MaintenanceAction = {
    key: string;
    label: string;
    description: string;
};

export type CommandRunStatus = 'pending' | 'running' | 'completed' | 'failed';

export type CommandRun = {
    id: number;
    action: string;
    command: string;
    status: CommandRunStatus;
    status_label: string;
    progress: number;
    output: string | null;
    exit_code: number | null;
    error_message: string | null;
    finished: boolean;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
};

export type MaintenanceConfig = {
    enabled: boolean;
    mode: boolean;
    actions: MaintenanceAction[];
    runs: CommandRun[];
};
