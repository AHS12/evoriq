import { Combobox } from '@/components/app/combobox';
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

type Props = {
    field: SettingFieldType;
    error?: string;
};

/**
 * Select fields with more options than this render as a searchable combobox.
 */
const SEARCHABLE_THRESHOLD = 10;

export function SettingField({ field, error }: Props) {
    const id = `setting-${field.key}`;
    const options = field.options ?? [];
    const searchableSelect =
        field.type === 'select' && options.length > SEARCHABLE_THRESHOLD;
    const booleanChecked =
        field.value === true || field.value === 1 || field.value === '1';

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{field.label}</Label>

            {searchableSelect ? (
                <Combobox
                    id={id}
                    name={field.key}
                    options={options}
                    defaultValue={
                        field.value != null ? String(field.value) : undefined
                    }
                    placeholder="Select…"
                    searchPlaceholder="Search…"
                />
            ) : field.type === 'select' ? (
                <Select
                    name={field.key}
                    defaultValue={
                        field.value != null ? String(field.value) : undefined
                    }
                >
                    <SelectTrigger id={id} className="w-full">
                        <SelectValue placeholder="Select…" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
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
                        {booleanChecked ? 'Enabled' : 'Disabled'}
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
                            ? '•••••••• (leave blank to keep)'
                            : 'Not set'
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
                    {field.description}
                </p>
            )}

            <InputError message={error} />
        </div>
    );
}
