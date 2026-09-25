import type { JobOptions } from './data-processing';
import type { CursorPaginated } from './pagination';

export type AuditChannel = 'auth' | 'security' | 'rbac' | 'settings' | 'domain';

export type AuditLog = {
    id: number;
    description: string;
    event: string | null;
    channel: AuditChannel | null;
    subject: { type: string; id: number; label: string } | null;
    causer: { id: number; name: string; email: string } | null;
    properties: Record<string, unknown>;
    attribute_changes: {
        attributes?: Record<string, unknown>;
        old?: Record<string, unknown>;
    } | null;
    correlation_id: string | null;
    created_at: string;
};

export type AuditLogFilters = {
    search?: string | null;
    channel?: string | null;
    event?: string | null;
    subject_type?: string | null;
    causer_id?: string | number | null;
    date_from?: string | null;
    date_to?: string | null;
    order_by?: string | null;
    order_direction?: string | null;
    per_page?: string | number | null;
    cursor?: string | null;
};

export type AuditLogOption = {
    value: string;
    label: string;
};

export type AuditLogIndexProps = {
    logs: CursorPaginated<AuditLog>;
    filters: AuditLogFilters;
    channels: AuditLogOption[];
    events: AuditLogOption[];
    canViewAll: boolean;
    canManage: boolean;
    canExport: boolean;
    processingOptions: JobOptions;
};
