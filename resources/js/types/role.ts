export type Permission = {
    id: number;
    name: string;
    group: string | null;
    description: string | null;
    is_system: boolean;
};

export type Role = {
    id: number;
    name: string;
    guard_name: string;
    is_system: boolean;
    permissions?: string[];
    permissions_count?: number;
    users_count?: number;
    created_at?: string;
};
