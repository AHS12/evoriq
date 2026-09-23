import { Link, usePage } from '@inertiajs/react';
import { BarChart3, GitCompareArrows, RefreshCw } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ThemeToggle } from '@/components/app/theme-toggle';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const highlights: { icon: LucideIcon; text: string }[] = [
    { icon: RefreshCw, text: 'Sync your Clockify history' },
    { icon: BarChart3, text: 'Analyze trends across any period' },
    { icon: GitCompareArrows, text: 'Compare projects, people and clients' },
];

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { name } = usePage().props;
    const appName = name ?? 'Evoriq';

    return (
        <div className="relative grid min-h-svh lg:grid-cols-2">
            <div className="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex dark:border-r">
                <div
                    aria-hidden
                    className="pointer-events-none absolute -top-32 -left-24 size-[28rem] rounded-full bg-primary-foreground/5 blur-3xl"
                />

                <Link
                    href={home()}
                    className="relative z-10 flex items-center gap-2 text-lg font-medium"
                >
                    <AppLogoIcon className="size-8 fill-current text-primary-foreground" />
                    {appName}
                </Link>

                <div className="relative z-10 max-w-md space-y-6">
                    <h2 className="text-2xl font-semibold tracking-tight text-balance">
                        Historical time analytics for Clockify.
                    </h2>
                    <ul className="space-y-3 text-sm text-primary-foreground/70">
                        {highlights.map((highlight) => (
                            <li
                                key={highlight.text}
                                className="flex items-center gap-3"
                            >
                                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary-foreground/10">
                                    <highlight.icon className="size-4" />
                                </span>
                                {highlight.text}
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative z-10 text-xs text-primary-foreground/50">
                    &copy; {new Date().getFullYear()} {appName}
                </p>
            </div>

            <div className="relative flex flex-col justify-center px-6 py-12 lg:px-12">
                <div className="absolute top-4 right-4 lg:top-6 lg:right-6">
                    <ThemeToggle />
                </div>

                <div className="mx-auto flex w-full max-w-sm flex-col gap-6">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-semibold lg:hidden"
                    >
                        <AppLogoIcon className="size-8 fill-current text-foreground" />
                        <span>{appName}</span>
                    </Link>

                    <div className="space-y-1.5">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
