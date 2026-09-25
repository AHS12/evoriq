import { Form, Head, Link, usePage } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { AvatarForm } from '@/components/profile/avatar-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { useTranslation } from '@/hooks/use-translation';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const { t } = useTranslation();
    const user = auth.user;

    if (!user) {
        return null;
    }

    return (
        <>
            <Head title={t('Profile')} />

            <h1 className="sr-only">{t('Profile settings')}</h1>

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Avatar')}</CardTitle>
                        <CardDescription>
                            {t('Upload a photo to personalize your account.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <AvatarForm />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Profile information')}</CardTitle>
                        <CardDescription>
                            {t('Update your name and email address.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...ProfileController.update.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            className="space-y-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {t('Name')}
                                        </Label>

                                        <Input
                                            id="name"
                                            className="mt-1 block w-full"
                                            defaultValue={user.name}
                                            name="name"
                                            required
                                            autoComplete="name"
                                            placeholder={t('Full name')}
                                        />

                                        <InputError
                                            className="mt-2"
                                            message={errors.name}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            {t('Email address')}
                                        </Label>

                                        <Input
                                            id="email"
                                            type="email"
                                            className="mt-1 block w-full"
                                            defaultValue={user.email}
                                            name="email"
                                            required
                                            autoComplete="username"
                                            placeholder={t('Email address')}
                                        />

                                        <InputError
                                            className="mt-2"
                                            message={errors.email}
                                        />
                                    </div>

                                    {mustVerifyEmail &&
                                        user.email_verified_at === null && (
                                            <div>
                                                <p className="-mt-4 text-sm text-muted-foreground">
                                                    {t(
                                                        'Your email address is unverified.',
                                                    )}{' '}
                                                    <Link
                                                        href={send()}
                                                        as="button"
                                                        className="text-foreground underline decoration-muted-foreground/50 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                                                    >
                                                        {t(
                                                            'Click here to re-send the verification email.',
                                                        )}
                                                    </Link>
                                                </p>

                                                {status ===
                                                    'verification-link-sent' && (
                                                    <div className="mt-2 text-sm font-medium text-success">
                                                        {t(
                                                            'A new verification link has been sent to your email address.',
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        )}

                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        {processing && <Spinner />}
                                        {t('Save')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {!auth.isSuperAdmin && <DeleteUser />}
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile',
            href: edit(),
        },
    ],
};
