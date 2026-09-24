import type { Auth, Can } from '@/types/auth';
import type { NotificationSummary } from '@/types/notification';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            can: Can;
            sidebarOpen: boolean;
            notifications: NotificationSummary;
            activeJobs: number;
            [key: string]: unknown;
        };
    }
}
