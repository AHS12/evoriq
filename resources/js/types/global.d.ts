import type { Auth, Can } from '@/types/auth';
import type { I18n } from '@/types/i18n';
import type { NotificationSummary } from '@/types/notification';
import type { RowData } from '@tanstack/react-table';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@tanstack/react-table' {
    // Augmented so table columns can carry a translated label for the
    // FND-07 column-visibility menu, and opt out of hiding.
    interface ColumnMeta<TData extends RowData, TValue> {
        label?: string;
        hideable?: boolean;
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
