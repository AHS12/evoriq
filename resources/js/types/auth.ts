import type { User } from './user';

export type Auth = {
    user: User | null;
    roles: string[];
    permissions: string[];
    isSuperAdmin: boolean;
};

export type Can = Record<string, boolean>;

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
