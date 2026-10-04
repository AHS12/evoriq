import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as auditIndex } from '@/routes/audit-logs';
import { useTranslation } from '@/hooks/use-translation';
import type { PipelineRecentRun } from '@/types';

type Props = {
    runs: PipelineRecentRun[];
};

export function CorrelationSearch({ runs }: Props) {
    const { t } = useTranslation();
    const [value, setValue] = useState('');

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        const term = value.trim();

        if (term === '') {
            return;
        }

        const match = runs.find((run) => run.job_id.startsWith(term));

        if (match) {
            router.visit(match.action_url);

            return;
        }

        router.visit(auditIndex.url({ query: { search: term } }));
    };

    return (
        <form onSubmit={submit} className="flex gap-2">
            <Input
                value={value}
                onChange={(event) => setValue(event.target.value)}
                placeholder={t('Correlation id or job id')}
                aria-label={t('Correlation id or job id')}
            />
            <Button type="submit" variant="outline">
                {t('Search')}
            </Button>
        </form>
    );
}
