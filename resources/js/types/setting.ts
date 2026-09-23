export type SettingType =
    | 'string'
    | 'integer'
    | 'float'
    | 'boolean'
    | 'json'
    | 'array'
    | 'select';

export type SettingOption = {
    label: string;
    value: string;
};

export type SettingField = {
    key: string;
    label: string;
    description: string | null;
    type: SettingType;
    group: string;
    value: string | number | boolean | null;
    is_secret: boolean;
    has_value: boolean;
    options?: SettingOption[];
};

export type SettingGroup = {
    key: string;
    label: string;
    description?: string | null;
    fields: SettingField[];
};
