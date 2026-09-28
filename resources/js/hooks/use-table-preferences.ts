import { useCallback, useEffect, useRef, useState } from 'react';
import {
    createSavedView,
    defaultTablePreferences,
    readTablePreferences,
    writeTablePreferences,
    type SavedViewFilterInput,
    type TableDensity,
    type TablePreferences,
    type TableSavedView,
} from '@/lib/table-preferences';

export type SavedViewFilters = SavedViewFilterInput;

export type TablePreferencesControls = ReturnType<typeof useTablePreferences>;

/**
 * FND-07 — reads and persists a table's UI preferences (density, sticky
 * header, column visibility, saved views) under a stable `tableId`.
 *
 * Hydration happens in an effect so server rendering never touches storage;
 * persistence is skipped until the first read has completed, so stored values
 * are never overwritten with defaults.
 */
export function useTablePreferences(tableId: string) {
    const [preferences, setPreferences] = useState<TablePreferences>(
        defaultTablePreferences,
    );
    const hydratedRef = useRef(false);

    useEffect(() => {
        hydratedRef.current = false;
        setPreferences(readTablePreferences(tableId));
        hydratedRef.current = true;
    }, [tableId]);

    useEffect(() => {
        if (!hydratedRef.current) {
            return;
        }

        writeTablePreferences(tableId, preferences);
    }, [tableId, preferences]);

    const setDensity = useCallback((density: TableDensity) => {
        setPreferences((current) => ({ ...current, density }));
    }, []);

    const setStickyHeader = useCallback((stickyHeader: boolean) => {
        setPreferences((current) => ({ ...current, stickyHeader }));
    }, []);

    const setColumnVisibility = useCallback(
        (columnVisibility: Record<string, boolean>) => {
            setPreferences((current) => ({ ...current, columnVisibility }));
        },
        [],
    );

    const toggleColumn = useCallback((columnId: string, visible: boolean) => {
        setPreferences((current) => ({
            ...current,
            columnVisibility: {
                ...current.columnVisibility,
                [columnId]: visible,
            },
        }));
    }, []);

    const saveView = useCallback(
        (name: string, filters: SavedViewFilters): TableSavedView => {
            const view = createSavedView(name, filters);

            setPreferences((current) => ({
                ...current,
                views: [...current.views, view],
            }));

            return view;
        },
        [],
    );

    const renameView = useCallback((id: string, name: string) => {
        const trimmed = name.trim();

        if (!trimmed) {
            return;
        }

        setPreferences((current) => ({
            ...current,
            views: current.views.map((view) =>
                view.id === id ? { ...view, name: trimmed } : view,
            ),
        }));
    }, []);

    const deleteView = useCallback((id: string) => {
        setPreferences((current) => ({
            ...current,
            views: current.views.filter((view) => view.id !== id),
        }));
    }, []);

    const resetPreferences = useCallback(() => {
        setPreferences(defaultTablePreferences());
    }, []);

    return {
        density: preferences.density,
        stickyHeader: preferences.stickyHeader,
        columnVisibility: preferences.columnVisibility,
        views: preferences.views,
        setDensity,
        setStickyHeader,
        setColumnVisibility,
        toggleColumn,
        saveView,
        renameView,
        deleteView,
        resetPreferences,
    };
}
