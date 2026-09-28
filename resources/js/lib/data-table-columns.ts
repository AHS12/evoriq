import type { ColumnDef } from '@tanstack/react-table';

export type ColumnOption = {
    id: string;
    label: string;
};

type ColumnMetaShape = {
    label?: string;
    hideable?: boolean;
};

function columnId<TData, TValue>(
    column: ColumnDef<TData, TValue>,
): string | null {
    if (typeof column.id === 'string' && column.id !== '') {
        return column.id;
    }

    const accessor = 'accessorKey' in column ? column.accessorKey : undefined;

    if (typeof accessor === 'string') {
        return accessor;
    }

    if (Array.isArray(accessor)) {
        return accessor.join('.');
    }

    return null;
}

/**
 * FND-07 — derive the user-toggleable columns from a table's column defs.
 * Columns marked `enableHiding: false` (or `meta.hideable: false`) are skipped
 * so row actions / selection never disappear. Labels come from `meta.label`.
 */
export function getHideableColumns<TData, TValue>(
    columns: ColumnDef<TData, TValue>[],
): ColumnOption[] {
    const options: ColumnOption[] = [];

    for (const column of columns) {
        if (column.enableHiding === false) {
            continue;
        }

        const meta = (column.meta ?? {}) as ColumnMetaShape;

        if (meta.hideable === false) {
            continue;
        }

        const id = columnId(column);

        if (!id) {
            continue;
        }

        options.push({ id, label: meta.label ?? id });
    }

    return options;
}
