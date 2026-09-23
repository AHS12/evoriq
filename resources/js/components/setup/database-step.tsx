import { router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    databaseFormSchema,
    type DatabaseFormValues,
} from '@/lib/schemas/setup';
import { database as storeDatabase } from '@/routes/setup';
import { test as testDatabase } from '@/routes/setup/database';
import type { DatabaseDefaults, DatabaseDriverOption } from '@/types/setup';

type Props = {
    drivers: DatabaseDriverOption[];
    defaults: DatabaseDefaults;
    onComplete: () => void;
};

export function DatabaseStep({ drivers, defaults, onComplete }: Props) {
    const { form, errors, validate, setField } = useZodForm<DatabaseFormValues>(
        {
            driver: defaults.driver,
            host: defaults.host,
            port: defaults.port,
            database: defaults.database,
            username: defaults.username,
            password: defaults.password,
            create_database: true,
        },
        databaseFormSchema,
    );

    const selected = drivers.find(
        (driver) => driver.value === form.data.driver,
    );
    const isSqlite = form.data.driver === 'sqlite';

    const selectDriver = (value: string) => {
        setField('driver', value);

        const option = drivers.find((driver) => driver.value === value);

        if (option?.defaultPort) {
            setField('port', option.defaultPort);
        }

        if (value === 'sqlite') {
            setField('database', 'database/database.sqlite');
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!validate()) {
            return;
        }

        form.post(storeDatabase.url(), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => onComplete(),
        });
    };

    const testConnection = () => {
        if (!validate()) {
            return;
        }

        router.post(testDatabase.url(), form.data, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-xl font-semibold">Database</h2>
                <p className="text-sm text-muted-foreground">
                    Where Evoriq stores its synchronized data. SQLite works out
                    of the box with no server required.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="driver">Driver</Label>
                <Select value={form.data.driver} onValueChange={selectDriver}>
                    <SelectTrigger id="driver" className="w-full">
                        <SelectValue placeholder="Select a driver" />
                    </SelectTrigger>
                    <SelectContent>
                        {drivers.map((driver) => (
                            <SelectItem
                                key={driver.value}
                                value={driver.value}
                                disabled={!driver.available}
                            >
                                {driver.label}
                                {!driver.available && ' (extension missing)'}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.driver} />
            </div>

            {!isSqlite && (
                <>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="host">Host</Label>
                            <Input
                                id="host"
                                value={form.data.host}
                                onChange={(event) =>
                                    setField('host', event.target.value)
                                }
                                autoComplete="off"
                                aria-invalid={Boolean(errors.host)}
                            />
                            <InputError message={errors.host} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="port">Port</Label>
                            <Input
                                id="port"
                                type="number"
                                value={form.data.port}
                                onChange={(event) =>
                                    setField('port', Number(event.target.value))
                                }
                                aria-invalid={Boolean(errors.port)}
                            />
                            <InputError message={errors.port} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="database">Database name</Label>
                        <Input
                            id="database"
                            value={form.data.database}
                            onChange={(event) =>
                                setField('database', event.target.value)
                            }
                            autoComplete="off"
                            aria-invalid={Boolean(errors.database)}
                        />
                        <InputError message={errors.database} />
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="username">Username</Label>
                            <Input
                                id="username"
                                value={form.data.username}
                                onChange={(event) =>
                                    setField('username', event.target.value)
                                }
                                autoComplete="off"
                                aria-invalid={Boolean(errors.username)}
                            />
                            <InputError message={errors.username} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) =>
                                    setField('password', event.target.value)
                                }
                                autoComplete="new-password"
                            />
                            <InputError message={errors.password} />
                        </div>
                    </div>
                </>
            )}

            {isSqlite && (
                <div className="grid gap-2">
                    <Label htmlFor="database">Database file</Label>
                    <Input
                        id="database"
                        value={form.data.database}
                        onChange={(event) =>
                            setField('database', event.target.value)
                        }
                        autoComplete="off"
                        placeholder="database/database.sqlite"
                    />
                    <p className="text-xs text-muted-foreground">
                        Created automatically if it does not exist.
                    </p>
                    <InputError message={errors.database} />
                </div>
            )}

            {!isSqlite && (
                <label className="flex items-start gap-3 rounded-lg border p-3 text-sm">
                    <Checkbox
                        checked={form.data.create_database}
                        onCheckedChange={(checked) =>
                            setField('create_database', checked === true)
                        }
                        className="mt-0.5"
                    />
                    <span>
                        Create the database if it does not exist
                        <span className="block text-xs text-muted-foreground">
                            Requires a user with permission to create databases.
                        </span>
                    </span>
                </label>
            )}

            <div className="flex justify-between pt-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={testConnection}
                    disabled={form.processing}
                >
                    Test connection
                </Button>
                <Button type="submit" disabled={form.processing || !selected}>
                    {form.processing && <Spinner />}
                    Save &amp; continue
                </Button>
            </div>
        </form>
    );
}
