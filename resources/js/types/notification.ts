export type NotificationPriority = 'info' | 'success' | 'warning' | 'critical';

export type NotificationItem = {
    id: number;
    type: string;
    type_label: string;
    icon: string | null;
    priority: NotificationPriority;
    priority_label: string;
    title: string;
    body: string | null;
    action_url: string | null;
    data: Record<string, unknown> | null;
    group_key: string | null;
    is_read: boolean;
    created_at: string | null;
    created_at_diff: string | null;
};

export type NotificationPreferences = {
    inapp: boolean;
    sound: boolean;
    desktop: boolean;
    muted_types: string[];
};

export type NotificationSummary = {
    unread_count: number;
    recent: NotificationItem[];
    preferences: NotificationPreferences;
};

export type NotificationTypeOption = {
    value: string;
    label: string;
};

export type NotificationPriorityOption = {
    value: NotificationPriority;
    label: string;
};

export type NotificationFilter = {
    search: string | null;
    unread: boolean;
    priority: NotificationPriority | null;
    order_by: string;
    order_direction: string;
    per_page: number;
};
