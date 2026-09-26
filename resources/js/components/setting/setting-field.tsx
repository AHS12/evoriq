import { Combobox } from '@/components/app/combobox';
import { LanguageSelect } from '@/components/app/language-select';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SettingField as SettingFieldType } from '@/types';
import { useTranslation } from '@/hooks/use-translation';
import { useState } from 'react';

type Props = {
    field: SettingFieldType;
    error?: string;
};

/**
 * Select fields with more options than this render as a searchable combobox.
 */
const SEARCHABLE_THRESHOLD = 10;

export function SettingField({ field, error }: Props) {
    const { t } = useTranslation();
    const id = `setting-${field.key}`;
    const options = field.options ?? [];
    const searchableSelect =
        field.type === 'select' && options.length > SEARCHABLE_THRESHOLD;
    const booleanChecked =
        field.value === true || field.value === 1 || field.value === '1';
    const [selectValue, setSelectValue] = useState<string | undefined>(
        field.value != null ? String(field.value) : undefined,
    );

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{t(field.label)}</Label>

            {searchableSelect ? (
                <Combobox
                    id={id}
                    name={field.key}
                    options={options}
                    defaultValue={
                        field.value != null ? String(field.value) : undefined
                    }
                    placeholder={t('Select…')}
                    searchPlaceholder={t('Search…')}
                />
            ) : field.key === 'app_locale' ? (
                // The application language is a live per-user switch shared
                // with the header dropdown — it saves and reloads on change
                // and is intentionally not part of this form's payload.
                <LanguageSelect className="w-full" />
            ) : field.type === 'select' ? (
                <Select
                    name={field.key}
                    value={selectValue}
                    onValueChange={setSelectValue}
                >
                    <SelectTrigger id={id} className="w-full">
                        <SelectValue placeholder={t('Select…')} />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {t(option.label)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : field.type === 'boolean' ? (
                <div className="flex h-9 items-center gap-2">
                    <input type="hidden" name={field.key} value="0" />
                    <Checkbox
                        id={id}
                        name={field.key}
                        value="1"
                        defaultChecked={booleanChecked}
                    />
                    <span className="text-sm text-muted-foreground">
                        {booleanChecked ? t('Enabled') : t('Disabled')}
                    </span>
                </div>
            ) : field.is_secret ? (
                <Input
                    id={id}
                    name={field.key}
                    type="password"
                    autoComplete="new-password"
                    placeholder={
                        field.has_value
                            ? t('•••••••• (leave blank to keep)')
                            : t('Not set')
                    }
                />
            ) : (
                <Input
                    id={id}
                    name={field.key}
                    type={field.type === 'integer' ? 'number' : 'text'}
                    defaultValue={
                        field.value != null ? String(field.value) : ''
                    }
                />
            )}

            {field.description && (
                <p className="text-xs text-muted-foreground">
                    {t(field.description)}
                </p>
            )}

            <InputError message={error} />
        </div>
    );
}
