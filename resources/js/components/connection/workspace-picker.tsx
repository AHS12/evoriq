import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { WorkspaceOption } from '@/types';

type Props = {
    workspaces: WorkspaceOption[];
    value: string | null;
    onChange: (clockifyId: string) => void;
};

function isFree(workspace: WorkspaceOption): boolean {
    return (workspace.feature_subscription_type ?? '')
        .toUpperCase()
        .includes('FREE');
}

/**
 * CONN-04 — the workspace radio picker. A native radiogroup so keyboard and
 * screen-reader behaviour comes for free.
 */
export function WorkspacePicker({ workspaces, value, onChange }: Props) {
    const { t } = useTranslation();

    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">
                {t('Choose a workspace')}
            </legend>

            <div role="radiogroup" aria-label={t('Choose a workspace')}>
                {workspaces.map((workspace) => {
                    const active = value === workspace.clockify_id;
                    const details = [workspace.time_zone, workspace.currency]
                        .filter((detail): detail is string => Boolean(detail))
                        .join(' · ');

                    return (
                        <label
                            key={workspace.clockify_id}
                            className={cn(
                                'mb-2 flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-smooth-fast hover:bg-muted/40',
                                active && 'border-primary ring-1 ring-primary',
                            )}
                        >
                            <input
                                type="radio"
                                name="workspace"
                                value={workspace.clockify_id}
                                checked={active}
                                onChange={() => onChange(workspace.clockify_id)}
                                className="size-4 accent-[var(--primary)]"
                            />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium">
                                    {workspace.name}
                                </span>
                                {details && (
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {details}
                                    </span>
                                )}
                            </span>
                            <Badge variant="outline">
                                {isFree(workspace) ? t('Free') : t('Paid')}
                            </Badge>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
