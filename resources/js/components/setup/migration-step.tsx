import { Check } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { migrate as runMigrations } from '@/routes/setup';

type Props = {
    migrated: boolean;
    seeded: boolean;
    onComplete: () => void;
};

export function MigrationStep({ migrated, seeded, onComplete }: Props) {
    const { post, processing, errors } = useForm({ migrate: '' });

    const run = () => {
        post(runMigrations.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onComplete(),
        });
    };

    return (
        <div className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-xl font-semibold">Set up the database</h2>
                <p className="text-sm text-muted-foreground">
                    Creates the tables and seeds roles, permissions and default
                    settings.
                </p>
            </div>

            <ul className="divide-y rounded-lg border text-sm">
                <li className="flex items-center justify-between p-3">
                    <span>Schema migrated</span>
                    <Status done={migrated} />
                </li>
                <li className="flex items-center justify-between p-3">
                    <span>Roles &amp; settings seeded</span>
                    <Status done={seeded} />
                </li>
            </ul>

            <InputError message={errors.migrate} />

            <div className="flex justify-end pt-2">
                <Button onClick={run} disabled={processing}>
                    {processing && <Spinner />}
                    {migrated ? 'Run migrations again' : 'Run migrations'}
                </Button>
            </div>
        </div>
    );
}

function Status({ done }: { done: boolean }) {
    return done ? (
        <span className="flex items-center gap-1.5 text-sm font-medium text-primary">
            <Check className="size-4" /> Done
        </span>
    ) : (
        <span className="text-xs text-muted-foreground">Pending</span>
    );
}
