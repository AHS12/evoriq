import { usePage } from '@inertiajs/react';
import { PageHeader } from '@/components/app/page-header';
import AppLayout from '@/layouts/app-layout';
import AuthSplitLayout from '@/layouts/auth/auth-split-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { auth } = usePage().props;

    // Authenticated users (password confirmation, email verification) stay
    // inside the application shell. The split layout is reserved for guests.
    if (auth.user) {
        return (
            <AppLayout>
                <div className="mx-auto w-full max-w-md p-4 md:p-6">
                    <PageHeader title={title} description={description} />
                    <div className="mt-6">{children}</div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AuthSplitLayout title={title} description={description}>
            {children}
        </AuthSplitLayout>
    );
}
