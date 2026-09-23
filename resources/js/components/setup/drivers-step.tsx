import type { FormEvent } from 'react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useZodForm } from '@/hooks/use-zod-form';
import { driverFormSchema, type DriverFormValues } from '@/lib/schemas/setup';
import { drivers as storeDrivers } from '@/routes/setup';
import type { DriverDefaults, DriverOptions, RedisStatus } from '@/types/setup';

type Props = {
    options: DriverOptions;
    defaults: DriverDefaults;
    redis: RedisStatus;
    onComplete: () => void;
};

export function DriversStep({ options, defaults, redis, onComplete }: Props) {
    const { form, errors, validate, setField } = useZodForm<DriverFormValues>(
        {
            session: defaults.session,
            cache: defaults.cache,
            queue: defaults.queue,
            redis_host: defaults.redisHost,
            redis_port: defaults.redisPort,
            redis_password: defaults.redisPassword,
        },
        driverFormSchema,
    );

    const usesRedis =
        form.data.session === 'redis' ||
        form.data.cache === 'redis' ||
        form.data.queue === 'redis';

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!validate()) {
            return;
        }

        form.post(storeDrivers.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onComplete(),
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-xl font-semibold">Runtime drivers</h2>
                <p className="text-sm text-muted-foreground">
                    Sessions, cache and queued jobs. Redis is faster when
                    available; the database works everywhere.
                </p>
            </div>

            {redis.available ? (
                <Alert>
                    <CircleCheck />
                    <AlertTitle>Redis detected</AlertTitle>
                    <AlertDescription>
                        {redis.message} We recommend Redis for all three
                        drivers.
                    </AlertDescription>
                </Alert>
            ) : (
                <Alert>
                    <TriangleAlert />
                    <AlertTitle>Redis unavailable</AlertTitle>
                    <AlertDescription>
                        {redis.message} Falling back to the database is fine.
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid gap-4 sm:grid-cols-3">
                <DriverSelect
                    id="session"
                    label="Sessions"
                    value={form.data.session}
                    options={options.session}
                    error={errors.session}
                    onChange={(value) => setField('session', value)}
                />
                <DriverSelect
                    id="cache"
                    label="Cache"
                    value={form.data.cache}
                    options={options.cache}
                    error={errors.cache}
                    onChange={(value) => setField('cache', value)}
                />
                <DriverSelect
                    id="queue"
                    label="Queue"
                    value={form.data.queue}
                    options={options.queue}
                    error={errors.queue}
                    onChange={(value) => setField('queue', value)}
                />
            </div>

            {usesRedis && (
                <div className="grid gap-2 sm:grid-cols-3">
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="redis_host">Redis host</Label>
                        <Input
                            id="redis_host"
                            value={form.data.redis_host}
                            onChange={(event) =>
                                setField('redis_host', event.target.value)
                            }
                            autoComplete="off"
                        />
                        <InputError message={errors.redis_host} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="redis_port">Port</Label>
                        <Input
                            id="redis_port"
                            type="number"
                            value={form.data.redis_port}
                            onChange={(event) =>
                                setField(
                                    'redis_port',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={errors.redis_port} />
                    </div>
                    <div className="grid gap-2 sm:col-span-3">
                        <Label htmlFor="redis_password">
                            Redis password (optional)
                        </Label>
                        <Input
                            id="redis_password"
                            type="password"
                            value={form.data.redis_password}
                            onChange={(event) =>
                                setField('redis_password', event.target.value)
                            }
                            autoComplete="new-password"
                        />
                        <InputError message={errors.redis_password} />
                    </div>
                </div>
            )}

            <div className="flex justify-end pt-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    Save &amp; continue
                </Button>
            </div>
        </form>
    );
}

type DriverSelectProps = {
    id: string;
    label: string;
    value: string;
    options: { value: string; label: string }[];
    error?: string;
    onChange: (value: string) => void;
};

function DriverSelect({
    id,
    label,
    value,
    options,
    error,
    onChange,
}: DriverSelectProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
