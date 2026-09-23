export type UserStatus = 'invited' | 'active' | 'suspended';

export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    status?: UserStatus;
    status_label?: string;
    email_verified_at: string | null;
    roles?: string[];
    created_at: string;
    updated_at?: string;
};
