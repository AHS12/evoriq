import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { useZodForm } from '@/hooks/use-zod-form';
import {
    exportJobSchema,
    type ExportJobValues,
} from '@/lib/schemas/data-processing';
import { storeExport } from '@/routes/activity';
import type { JobOptions } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: JobOptions;
    /**
     * When set, the entity picker is hidden and fixed to this entity (used by
     * module pages such as Users).
     */
    lockEntity?: string;
    /** Extra export parameters (e.g. the list's current filters). */
    parameters?: Record<string, unknown>;
};

type FormProps = {
    options: JobOptions;
    lockEntity?: string;
    parameters: Record<string, unknown>;
    onCancel: () => void;
    onSuccess: () => void;
};

function ExportForm({
    options,
    lockEntity,
    parameters,
    onCancel,
    onSuccess,
}: FormProps) {
    const { t } = useTranslation();

    const entities = options.entities.filter(
        (entity) =>
            entity.export &&
            (lockEntity === undefined || entity.value === lockEntity),
    );

    const { form, errors, validate, setField } = useZodForm<ExportJobValues>(
        {
            entity_type: lockEntity ?? entities[0]?.value ?? '',
            format: 'xlsx',
        },
        exportJobSchema,
    );

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (!validate()) {
            return;
        }

        form.transform((data) => ({ ...data, filters: parameters }));
        form.post(storeExport.url(), {
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            {lockEntity === undefined && (
                <div className="grid gap-2">
                    <Label htmlFor="export-entity">{t('Entity')}</Label>
                    <Select
                        value={form.data.entity_type}
                        onValueChange={(value) =>
                            setField('entity_type', value)
                        }
                    >
                        <SelectTrigger id="export-entity">
                            <SelectValue placeholder={t('Choose an entity')} />
                        </SelectTrigger>
                        <SelectContent>
                            {entities.map((entity) => (
                                <SelectItem
                                    key={entity.value}
                                    value={entity.value}
                                >
                                    {entity.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.entity_type} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="export-format">{t('Format')}</Label>
                <Select
                    value={form.data.format}
                    onValueChange={(value) =>
                        setField('format', value === 'csv' ? 'csv' : 'xlsx')
                    }
                >
                    <SelectTrigger id="export-format">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {options.formats.map((format) => (
                            <SelectItem key={format.value} value={format.value}>
                                {format.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.format} />
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={form.processing}
                >
                    {t('Cancel')}
                </Button>
                <Button type="submit" loading={form.processing}>
                    {t('Export')}
                </Button>
            </DialogFooter>
        </form>
    );
}

export function ExportDialog({
    open,
    onOpenChange,
    options,
    lockEntity,
    parameters = {},
}: Props) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('New export')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            "Generate a file in the background. We'll notify you when it's ready.",
                        )}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <ExportForm
                        options={options}
                        lockEntity={lockEntity}
                        parameters={parameters}
                        onCancel={() => onOpenChange(false)}
                        onSuccess={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
