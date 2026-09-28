import type { Auth, Can } from '@/types/auth';
import type { I18n } from '@/types/i18n';
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
            i18n: I18n;
            notifications: NotificationSummary;
            activeJobs: number;
            pipeline_revision: string;
            [key: string]: unknown;
        };
    }
}
