import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import type { NotificationPreferences, NotificationTypeOption } from '@/types';

type FormDefinition = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

type PreferenceData = {
    inapp: boolean;
    sound: boolean;
    desktop: boolean;
    muted_types: string[];
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
    const [data, setData] = useState<PreferenceData>({
        inapp: preferences.inapp,
        sound: preferences.sound,
        desktop: preferences.desktop,
        muted_types: preferences.muted_types,
    });
    const [saving, setSaving] = useState(false);
    const [desktopBlocked, setDesktopBlocked] = useState(
        'Notification' in window && Notification.permission === 'denied',
    );

    // Every change is persisted immediately — preferences apply as you toggle.
    const persist = (next: PreferenceData): void => {
        router.patch(action.action, next, {
            preserveScroll: true,
            preserveState: true,
            onBefore: () => setSaving(true),
            onFinish: () => setSaving(false),
        });
    };

    const handleToggle = (
        key: 'inapp' | 'sound' | 'desktop',
        checked: boolean,
    ): void => {
        if (key !== 'desktop' || !checked) {
            const next = { ...data, [key]: checked };
            setData(next);
            persist(next);
            return;
        }

        if (!('Notification' in window)) {
            setDesktopBlocked(true);
            return;
        }

        if (Notification.permission === 'denied') {
            setDesktopBlocked(true);
            return;
        }

        if (Notification.permission === 'granted') {
            setDesktopBlocked(false);
            const next = { ...data, desktop: true };
            setData(next);
            persist(next);
            return;
        }

        void Notification.requestPermission().then((permission) => {
            if (permission === 'granted') {
                setDesktopBlocked(false);
                const next = { ...data, desktop: true };
                setData(next);
                persist(next);
            } else {
                setDesktopBlocked(permission === 'denied');
            }
        });
    };

    const toggleType = (value: string, checked: boolean): void => {
        const muted_types = checked
            ? [...data.muted_types, value]
            : data.muted_types.filter((type) => type !== value);

        const next = { ...data, muted_types };
        setData(next);
        persist(next);
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Delivery</CardTitle>
                    <CardDescription>
                        Choose how Evoriq gets your attention. Changes are saved
                        automatically.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    {TOGGLES.map((toggle) => (
                        <div key={toggle.key}>
                            <div className="flex items-center justify-between gap-4">
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
                                    disabled={saving}
                                    onCheckedChange={(checked) =>
                                        handleToggle(toggle.key, checked)
                                    }
                                />
                            </div>
                            {toggle.key === 'desktop' && desktopBlocked && (
                                <p className="mt-2 text-sm text-destructive">
                                    Browser notifications are blocked for this
                                    site. Allow them in your browser's site
                                    settings, then toggle this switch again.
                                </p>
                            )}
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
                                disabled={saving}
                                onCheckedChange={(checked) =>
                                    toggleType(type.value, checked === true)
                                }
                            />
                            {type.label}
                        </label>
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}
