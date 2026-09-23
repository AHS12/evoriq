import { Head, Link, usePage } from '@inertiajs/react';
import {
    FileQuestion,
    Gauge,
    ServerCrash,
    ShieldAlert,
    TimerOff,
    Wrench,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ThemeToggle } from '@/components/app/theme-toggle';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';

type Props = {
    status: number;
};

type ErrorContent = {
    icon: LucideIcon;
    title: string;
    description: string;
};

const content: Record<number, ErrorContent> = {
    403: {
        icon: ShieldAlert,
        title: 'Access denied',
        description:
            'You do not have permission to view this page. If you think this is a mistake, contact an administrator.',
    },
    404: {
        icon: FileQuestion,
        title: 'Page not found',
        description:
            'The page you are looking for does not exist or may have been moved.',
    },
    419: {
        icon: TimerOff,
        title: 'Page expired',
        description:
            'Your session has expired. Refresh the page and try again.',
    },
    429: {
        icon: Gauge,
        title: 'Too many requests',
        description:
            'You have made too many requests. Please wait a moment and try again.',
    },
    500: {
        icon: ServerCrash,
        title: 'Something went wrong',
        description:
            'An unexpected error occurred on our end. Please try again in a moment.',
    },
    503: {
        icon: Wrench,
        title: 'Under maintenance',
        description:
            'Evoriq is temporarily down for maintenance. Please check back shortly.',
    },
};

const fallback: ErrorContent = {
    icon: ServerCrash,
    title: 'Something went wrong',
    description: 'An unexpected error occurred. Please try again.',
};

export default function ErrorPage({ status }: Props) {
    const { name } = usePage().props;
    const appName = name ?? 'Evoriq';
    const { icon: Icon, title, description } = content[status] ?? fallback;

    return (
        <>
            <Head title={title} />

            <div className="flex min-h-svh flex-col bg-background">
                <header className="flex h-16 items-center justify-between px-4 sm:px-6">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-semibold"
                    >
                        <AppLogoIcon className="size-7 fill-current text-foreground" />
                        <span>{appName}</span>
                    </Link>
                    <ThemeToggle />
                </header>

                <main className="flex flex-1 items-center justify-center px-4 pb-16">
                    <div className="flex w-full max-w-md flex-col items-center text-center">
                        <div className="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                            <Icon className="size-7" />
                        </div>

                        <p className="mt-6 text-sm font-medium text-muted-foreground">
                            Error {status}
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="mt-3 text-sm text-pretty text-muted-foreground">
                            {description}
                        </p>

                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
                            <Button asChild>
                                <Link href={home()}>Back to home</Link>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.history.back()}
                            >
                                Go back
                            </Button>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
