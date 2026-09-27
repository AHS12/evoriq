export type BackupRunStatus = 'pending' | 'running' | 'completed' | 'failed';

export type BackupRunType = 'backup' | 'cleanup' | 'monitor';

export type BackupRunTrigger = 'scheduled' | 'manual';

export type BackupRun = {
    id: number;
    type: BackupRunType;
    type_label: string;
    status: BackupRunStatus;
    status_label: string;
    trigger: BackupRunTrigger;
    trigger_label: string;
    disk: string | null;
    file_name: string | null;
    file_size: number | null;
    downloadable: boolean;
    exit_code: number | null;
    error_message: string | null;
    output: string | null;
    finished: boolean;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
    user: { id: number; name: string } | null;
};

export type BackupStatus = {
    enabled: boolean;
    frequency: string;
    time: string;
    uses_remote: boolean;
    remote_configured: boolean;
    next_run_at: string | null;
    storage_used_mb: number | null;
    storage_max_mb: number;
    last_backup: BackupRun | null;
    health: string | null;
    running: boolean;
};

export type BackupDevStatus = {
    enabled: boolean;
    next_run_at: string | null;
    last_backup_at: string | null;
    last_backup_status: string | null;
    health: string | null;
    storage_used_mb: number | null;
    storage_max_mb: number;
};

export type RemoteStorage = {
    enabled: boolean;
    provider: string;
    access_key_id: string;
    has_secret: boolean;
    region: string;
    bucket: string;
    endpoint: string | null;
    use_path_style: boolean;
};
