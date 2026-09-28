import { CopyButton } from '@/components/app/copy-button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    value: unknown;
    /** Set when the backend truncated an oversized payload. */
    truncated?: boolean;
    className?: string;
};

/**
 * PIPE-06 — a dependency-free JSON viewer: a keyboard-scrollable `<pre>` with
 * copy. Truncated payloads are already strings; everything else is pretty
 * printed.
 */
export function RunJsonViewer({ value, truncated = false, className }: Props) {
    const { t } = useTranslation();
    const text =
        typeof value === 'string'
            ? value
            : JSON.stringify(value ?? {}, null, 2);

    return (
        <div className={cn('space-y-2', className)}>
            <pre
                tabIndex={0}
                className="max-h-64 overflow-auto rounded-md bg-muted p-2 text-xs whitespace-pre-wrap outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                {text}
            </pre>
            <div className="flex items-center justify-between gap-2">
                <CopyButton value={text} />
                {truncated && (
                    <span className="text-xs text-warning">
                        {t('Context truncated')}
                    </span>
                )}
            </div>
        </div>
    );
}
