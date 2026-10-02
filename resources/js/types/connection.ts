export type ConnectionStatus = 'active' | 'invalid' | 'disabled';

export type ConnectionPlanRate = {
    mode: 'hourly' | 'per_second';
    limit: number;
    requests_per_hour: number | null;
    requests_per_second: number | null;
};

export type ConnectionPlan = {
    subscription_type: string | null;
    is_free: boolean;
    rate: ConnectionPlanRate;
};

export type Connection = {
    id: number;
    name: string;
    region: string;
    region_label: string;
    status: ConnectionStatus;
    status_label: string;
    plan: ConnectionPlan;
    workspace: {
        id: string | null;
        subdomain: string | null;
    };
    webhook_limit: number | null;
    last_verified_at: string | null;
    created_at: string | null;
};

export type RegionOption = {
    value: string;
    label: string;
};

export type WorkspaceOption = {
    id?: number;
    clockify_id: string;
    name: string;
    subdomain: string | null;
    currency: string | null;
    time_zone: string | null;
    feature_subscription_type: string | null;
    active: boolean;
};

export type ConnectionProfile = {
    plan: 'free' | 'paid';
    requests_per_hour: number | null;
    requests_per_second: number | null;
    webhook_limit: number | null;
};

export type ConnectionError = {
    code: string | null;
    label: string | null;
    hint: string | null;
    action: string | null;
    retry_after: number | null;
};

export type ConnectionAccount = {
    name: string | null;
    email: string | null;
};

export type ConnectionVerification = {
    ok: boolean;
    error: ConnectionError | null;
    profile: ConnectionProfile | null;
    workspaces: WorkspaceOption[];
    account: ConnectionAccount | null;
};
