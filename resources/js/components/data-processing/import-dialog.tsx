import { useForm } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import type { ChangeEvent, DragEvent, FormEvent } from 'react';
import { useState } from 'react';
import { ImportTemplateDownload } from '@/components/data-processing/import-template-download';
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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { storeImport } from '@/routes/activity';
import type { JobOptions } from '@/types';

type FormState = {
    entity_type: string;
    file: File | null;
    send_invitations: boolean;
    default_role: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: JobOptions;
    /**
     * When set, the entity picker is hidden and fixed to this entity (used by
     * module pages such as Users).
     */
    lockEntity?: string;
};

function ImportForm({
    options,
    lockEntity,
    onCancel,
    onSuccess,
}: {
    options: JobOptions;
    lockEntity?: string;
    onCancel: () => void;
    onSuccess: () => void;
}) {
    const entities = options.entities.filter(
        (entity) =>
            entity.import &&
            (lockEntity === undefined || entity.value === lockEntity),
    );

    const form = useForm<FormState>({
        entity_type: lockEntity ?? entities[0]?.value ?? '',
        file: null,
        send_invitations: true,
        default_role: '',
    });

    const [fileError, setFileError] = useState<string | null>(null);

    const selectedEntity =
        entities.find((entity) => entity.value === form.data.entity_type) ??
        null;

    const setFile = (file: File | null): void => {
        form.setData('file', file);
        setFileError(file ? null : 'Choose a file to import.');
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (!form.data.file) {
            setFileError('Choose a file to import.');

            return;
        }

        form.post(storeImport.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            {lockEntity === undefined && (
                <div className="grid gap-2">
                    <Label htmlFor="import-entity">Entity</Label>
                    <Select
                        value={form.data.entity_type}
                        onValueChange={(value) =>
                            form.setData('entity_type', value)
                        }
                    >
                        <SelectTrigger id="import-entity">
                            <SelectValue placeholder="Choose an entity" />
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
                    <InputError message={form.errors.entity_type} />
                </div>
            )}

            <ImportTemplateDownload entity={selectedEntity} />

            <div className="grid gap-2">
                <Label htmlFor="import-file">File</Label>
                <label
                    htmlFor="import-file"
                    onDragOver={(event: DragEvent) => event.preventDefault()}
                    onDrop={(event: DragEvent) => {
                        event.preventDefault();
                        setFile(event.dataTransfer.files?.[0] ?? null);
                    }}
                    className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-6 text-center transition-colors hover:border-primary/50"
                >
                    <div className="flex size-9 items-center justify-center rounded-full bg-muted">
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <Upload className="size-4 text-muted-foreground" />
                        )}
                    </div>
                    <p className="text-sm font-medium">
                        {form.data.file
                            ? form.data.file.name
                            : 'Drag & drop a .csv or .xlsx file, or click to browse'}
                    </p>
                    <input
                        id="import-file"
                        type="file"
                        accept=".csv,.txt,.xlsx"
                        className="sr-only"
                        onChange={(event: ChangeEvent<HTMLInputElement>) =>
                            setFile(event.target.files?.[0] ?? null)
                        }
                    />
                </label>
                <InputError message={fileError ?? form.errors.file} />
            </div>

            <div className="flex items-center justify-between rounded-lg border p-3">
                <div>
                    <p className="text-sm font-medium">Send invitations</p>
                    <p className="text-xs text-muted-foreground">
                        Email new users a link to set their password.
                    </p>
                </div>
                <Switch
                    checked={form.data.send_invitations}
                    onCheckedChange={(checked) =>
                        form.setData('send_invitations', checked)
                    }
                />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="import-role">Default role</Label>
                <Select
                    value={
                        form.data.default_role === ''
                            ? 'none'
                            : form.data.default_role
                    }
                    onValueChange={(value) =>
                        form.setData(
                            'default_role',
                            value === 'none' ? '' : value,
                        )
                    }
                >
                    <SelectTrigger id="import-role">
                        <SelectValue placeholder="No role" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">No role</SelectItem>
                        {options.roles.map((role) => (
                            <SelectItem key={role} value={role}>
                                {role}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    Applied when a row doesn't specify its own roles.
                </p>
                <InputError message={form.errors.default_role} />
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={form.processing}
                >
                    Cancel
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner className="size-4" />}
                    Import
                </Button>
            </DialogFooter>
        </form>
    );
}

export function ImportDialog({
    open,
    onOpenChange,
    options,
    lockEntity,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>New import</DialogTitle>
                    <DialogDescription>
                        Upload a file in the background. We'll notify you when
                        it's done.
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <ImportForm
                        options={options}
                        lockEntity={lockEntity}
                        onCancel={() => onOpenChange(false)}
                        onSuccess={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
