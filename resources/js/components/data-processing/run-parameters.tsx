import { formatBytes } from '@/components/data-processing/job-utils';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
};

/**
 * PIPE-06 — the parameters a run used: entity, format, owner, input file and
 * the sanitized filters rendered as chips.
 */
export function RunParameters({ run }: Props) {
    const { t } = useTranslation();

    const rows = [
        { label: t('Entity'), value: run.entity_label ?? '—' },
        { label: t('Format'), value: run.format_label ?? '—' },
        { label: t('Owner'), value: run.owner ?? '—' },
        {
            label: t('Input file'),
            value: run.input_file_name
                ? `${run.input_file_name}${
                      run.file_size != null
                          ? ` · ${formatBytes(run.file_size)}`
                          : ''
                  }`
                : '—',
        },
    ];

    const filters = run.parameters
        ? Object.entries(run.parameters).filter(
              ([, value]) => value !== null && value !== '',
          )
        : [];

    return (
        <div className="space-y-4">
            <dl className="grid gap-3 sm:grid-cols-2">
                {rows.map((row) => (
                    <div key={row.label} className="min-w-0">
                        <dt className="text-xs text-muted-foreground">
                            {row.label}
                        </dt>
                        <dd className="truncate text-sm font-medium">
                            {row.value}
                        </dd>
                    </div>
                ))}
            </dl>

            <div className="space-y-2">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('Filters')}
                </h3>
                {filters.length > 0 ? (
                    <div className="flex flex-wrap gap-1.5">
                        {filters.map(([key, value]) => (
                            <Badge key={key} variant="outline">
                                {`${key.replace(/_/g, ' ')}: ${String(value)}`}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('No parameters were set.')}
                    </p>
                )}
            </div>
        </div>
    );
}
