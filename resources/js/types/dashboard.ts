export type DashboardStat = {
    key: string;
    label: string;
    value: string;
    description?: string | null;
};

export type SetupStepStatus = 'todo' | 'done' | 'coming_soon';

export type SetupStep = {
    key: string;
    label: string;
    description: string;
    status: SetupStepStatus;
};
