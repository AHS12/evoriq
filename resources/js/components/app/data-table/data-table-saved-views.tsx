import { Bookmark, Check, Pencil, Trash2 } from 'lucide-react';
import { useId, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import type { SavedViewFilters } from '@/hooks/use-table-preferences';
import type { TableSavedView } from '@/lib/table-preferences';
import { cn } from '@/lib/utils';

type Props = {
    views: TableSavedView[];
    filters: SavedViewFilters;
    onApply: (filters: SavedViewFilters) => void;
    onSave: (name: string, filters: SavedViewFilters) => void;
    onRename: (id: string, name: string) => void;
    onDelete: (id: string) => void;
    disabled?: boolean;
    className?: string;
};

type DialogState = { mode: 'save' } | { mode: 'rename'; view: TableSavedView };

function normalize(filters: SavedViewFilters): [string, string][] {
    return Object.entries(filters)
        .filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        )
        .map(([key, value]): [string, string] => [key, String(value)])
        .sort(([a], [b]) => a.localeCompare(b));
}

function filtersEqual(a: SavedViewFilters, b: SavedViewFilters): boolean {
    return JSON.stringify(normalize(a)) === JSON.stringify(normalize(b));
}

/** FND-07 — named filter presets for a table, stored per `tableId`. */
export function DataTableSavedViews({
    views,
    filters,
    onApply,
    onSave,
    onRename,
    onDelete,
    disabled,
    className,
}: Props) {
    const { t } = useTranslation();
    const inputId = useId();
    const [dialog, setDialog] = useState<DialogState | null>(null);
    const [name, setName] = useState('');

    const openSave = () => {
        setName('');
        setDialog({ mode: 'save' });
    };

    const openRename = (view: TableSavedView) => {
        setName(view.name);
        setDialog({ mode: 'rename', view });
    };

    const close = () => setDialog(null);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const trimmed = name.trim();

        if (!trimmed || !dialog) {
            return;
        }

        if (dialog.mode === 'save') {
            onSave(trimmed, filters);
        } else {
            onRename(dialog.view.id, trimmed);
        }

        close();
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={disabled}
                        className={cn(className)}
                    >
                        <Bookmark />
                        {t('Saved views')}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuLabel>{t('Saved views')}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {views.length === 0 ? (
                        <DropdownMenuItem disabled>
                            {t('No saved views yet')}
                        </DropdownMenuItem>
                    ) : (
                        views.map((view) => {
                            const active = filtersEqual(view.filters, filters);

                            return (
                                <DropdownMenuItem
                                    key={view.id}
                                    onSelect={() => onApply(view.filters)}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            !active && 'opacity-0',
                                        )}
                                    />
                                    <span className="truncate">
                                        {view.name}
                                    </span>
                                </DropdownMenuItem>
                            );
                        })
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onSelect={openSave}>
                        {t('Save current view…')}
                    </DropdownMenuItem>
                    {views.length > 0 && (
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                {t('Manage views')}
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                {views.map((view) => (
                                    <DropdownMenuSub key={view.id}>
                                        <DropdownMenuSubTrigger>
                                            <span className="truncate">
                                                {view.name}
                                            </span>
                                        </DropdownMenuSubTrigger>
                                        <DropdownMenuSubContent>
                                            <DropdownMenuItem
                                                onSelect={() =>
                                                    openRename(view)
                                                }
                                            >
                                                <Pencil />
                                                {t('Rename')}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                variant="destructive"
                                                onSelect={() =>
                                                    onDelete(view.id)
                                                }
                                            >
                                                <Trash2 />
                                                {t('Delete')}
                                            </DropdownMenuItem>
                                        </DropdownMenuSubContent>
                                    </DropdownMenuSub>
                                ))}
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {dialog?.mode === 'rename'
                                ? t('Rename view')
                                : t('Save view')}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4" noValidate>
                        <div className="grid gap-2">
                            <Label htmlFor={inputId}>{t('View name')}</Label>
                            <Input
                                id={inputId}
                                value={name}
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                maxLength={60}
                                autoFocus
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={close}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={!name.trim()}>
                                {dialog?.mode === 'rename'
                                    ? t('Rename')
                                    : t('Save')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
