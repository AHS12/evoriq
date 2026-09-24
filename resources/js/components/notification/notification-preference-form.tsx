import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import type { NotificationPreferences, NotificationTypeOption } from '@/types';

type FormDefinition = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

type Props = {
    preferences: NotificationPreferences;
    types: NotificationTypeOption[];
    action: FormDefinition;
};

const TOGGLES: {
    key: 'inapp' | 'sound' | 'desktop';
    label: string;
    description: string;
}[] = [
    {
        key: 'inapp',
        label: 'In-app toasts',
        description: 'Show a toast when a notification arrives.',
    },
    {
        key: 'sound',
        label: 'Sound',
        description: 'Play a subtle sound for new notifications.',
    },
    {
        key: 'desktop',
        label: 'Browser notifications',
        description: 'Show operating-system notifications.',
    },
];

export function NotificationPreferenceForm({
    preferences,
    types,
    action,
}: Props) {
    const { data, setData, patch, processing } = useForm({
        inapp: preferences.inapp,
        sound: preferences.sound,
        desktop: preferences.desktop,
        muted_types: preferences.muted_types,
    });

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (
            data.desktop &&
            'Notification' in window &&
            Notification.permission === 'default'
        ) {
            void Notification.requestPermission();
        }

        patch(action.action, { preserveScroll: true });
    };

    const toggleType = (value: string, checked: boolean): void => {
        setData(
            'muted_types',
            checked
                ? [...data.muted_types, value]
                : data.muted_types.filter((type) => type !== value),
        );
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Delivery</CardTitle>
                    <CardDescription>
                        Choose how Evoriq gets your attention.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {TOGGLES.map((toggle) => (
                        <div
                            key={toggle.key}
                            className="flex items-center justify-between gap-4"
                        >
                            <div className="space-y-0.5">
                                <Label htmlFor={`toggle-${toggle.key}`}>
                                    {toggle.label}
                                </Label>
                                <p className="text-sm text-muted-foreground">
                                    {toggle.description}
                                </p>
                            </div>
                            <Switch
                                id={`toggle-${toggle.key}`}
                                checked={data[toggle.key]}
                                onCheckedChange={(checked) =>
                                    setData(toggle.key, checked)
                                }
                            />
                        </div>
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Muted types</CardTitle>
                    <CardDescription>
                        Muted types still appear in your feed and unread count,
                        but never interrupt you.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-2">
                    {types.map((type) => (
                        <label
                            key={type.value}
                            htmlFor={`type-${type.value}`}
                            className="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                id={`type-${type.value}`}
                                checked={data.muted_types.includes(type.value)}
                                onCheckedChange={(checked) =>
                                    toggleType(type.value, checked === true)
                                }
                            />
                            {type.label}
                        </label>
                    ))}
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    Save preferences
                </Button>
            </div>
        </form>
    );
}
