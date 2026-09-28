import type { ColumnDef } from '@tanstack/react-table';
import { DataTableDensityToggle } from '@/components/app/data-table/data-table-density-toggle';
import { DataTableSavedViews } from '@/components/app/data-table/data-table-saved-views';
import { DataTableViewOptions } from '@/components/app/data-table/data-table-view-options';
import type {
    SavedViewFilters,
    TablePreferencesControls,
} from '@/hooks/use-table-preferences';
import { getHideableColumns } from '@/lib/data-table-columns';

type Props<TData, TValue> = {
    preferences: TablePreferencesControls;
    columns: ColumnDef<TData, TValue>[];
    filters?: SavedViewFilters;
    onApplyView?: (filters: SavedViewFilters) => void;
    disabled?: boolean;
};

/**
 * FND-07 — the toolbar controls cluster: saved views (when the page has
 * filters), row density and column visibility. The page owns the preferences
 * via `useTablePreferences` and passes the same object to `DataTable`.
 */
export function DataTableViewControls<TData, TValue>({
    preferences,
    columns,
    filters,
    onApplyView,
    disabled,
}: Props<TData, TValue>) {
    const hideableColumns = getHideableColumns(columns);

    return (
        <div className="flex flex-wrap items-center gap-2">
            {onApplyView && filters !== undefined && (
                <DataTableSavedViews
                    views={preferences.views}
                    filters={filters}
                    onApply={onApplyView}
                    onSave={preferences.saveView}
                    onRename={preferences.renameView}
                    onDelete={preferences.deleteView}
                    disabled={disabled}
                />
            )}
            <DataTableDensityToggle
                value={preferences.density}
                onChange={preferences.setDensity}
                disabled={disabled}
            />
            <DataTableViewOptions
                columns={hideableColumns}
                columnVisibility={preferences.columnVisibility}
                onToggle={preferences.toggleColumn}
                onReset={preferences.resetPreferences}
                disabled={disabled}
            />
        </div>
    );
}
