export type Requirement = {
    key: string;
    label: string;
    passed: boolean;
    detail: string;
    required: boolean;
};

export type SetupStatus = {
    environment: {
        env_exists: boolean;
        env_writable: boolean;
        key_set: boolean;
    };
    database: {
        configured: boolean;
        migrated: boolean;
        seeded: boolean;
    };
};

export type DatabaseDriverOption = {
    value: string;
    label: string;
    defaultPort: number | null;
    requiresCredentials: boolean;
    available: boolean;
};

export type DatabaseDefaults = {
    driver: string;
    host: string;
    port: number;
    database: string;
    username: string;
    password: string;
};

export type DriverOption = {
    value: string;
    label: string;
};

export type DriverOptions = {
    session: DriverOption[];
    cache: DriverOption[];
    queue: DriverOption[];
};

export type DriverDefaults = {
    session: string;
    cache: string;
    queue: string;
    redisHost: string;
    redisPort: number;
    redisPassword: string;
};

export type RedisStatus = {
    available: boolean;
    client: string | null;
    message: string;
};

export type RecommendedDrivers = {
    session: string;
    cache: string;
    queue: string;
    redis: boolean;
};

export type ExistingAdmin = {
    name: string;
    email: string;
};

export type AccountFormData = {
    app_name: string;
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
};

export type AccountFormErrors = Record<string, string | undefined>;

export type AccountField = keyof AccountFormData;
