import { router, usePage } from '@inertiajs/react';
import { Trash2, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    destroy as destroyAvatar,
    update as updateAvatar,
} from '@/routes/profile/avatar';

function initials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

export function AvatarForm() {
    const user = usePage().props.auth.user;
    const inputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string>();

    if (!user) {
        return null;
    }

    const upload = (file: File) => {
        setError(undefined);

        router.post(
            updateAvatar.url(),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onError: (errors) => setError(errors.avatar),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const remove = () => {
        setError(undefined);

        router.delete(destroyAvatar.url(), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
            <Avatar className="size-16">
                <AvatarImage src={user.avatar ?? undefined} alt={user.name} />
                <AvatarFallback className="text-lg">
                    {initials(user.name)}
                </AvatarFallback>
            </Avatar>

            <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        className="hidden"
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                upload(file);
                            }

                            event.target.value = '';
                        }}
                    />

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={processing}
                        onClick={() => inputRef.current?.click()}
                    >
                        {processing ? <Spinner /> : <Upload />}
                        Upload avatar
                    </Button>

                    {user.avatar && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={processing}
                            onClick={remove}
                        >
                            <Trash2 />
                            Remove
                        </Button>
                    )}
                </div>

                <p className="text-xs text-muted-foreground">
                    PNG, JPG or WebP, up to 2 MB.
                </p>

                <InputError message={error} />
            </div>
        </div>
    );
}
