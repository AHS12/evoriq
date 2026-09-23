import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarRange,
    FileSpreadsheet,
    GitCompareArrows,
    RefreshCw,
    ShieldCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ThemeToggle } from '@/components/app/theme-toggle';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, home, login } from '@/routes';

type Feature = {
    icon: LucideIcon;
    title: string;
    description: string;
};

const features: Feature[] = [
    {
        icon: RefreshCw,
        title: 'Syncs with Clockify',
        description:
            'Pull projects, clients, users and time entries into a local database you fully control.',
    },
    {
        icon: BarChart3,
        title: 'Historical analytics',
        description:
            'Trends, totals and breakdowns across any period — long after Clockify stops showing them.',
    },
    {
        icon: GitCompareArrows,
        title: 'Comparisons',
        description:
            'Compare periods, people, projects and clients side by side to see what actually changed.',
    },
    {
        icon: CalendarRange,
        title: 'Flexible reporting',
        description:
            'Build the views your business needs and save them for reuse, week after week.',
    },
    {
        icon: FileSpreadsheet,
        title: 'Exports',
        description:
            'Generate spreadsheet exports in the background and download them whenever you need.',
    },
    {
        icon: ShieldCheck,
        title: 'Permission-aware',
        description:
            'Role-based access keeps every report, export and setting in the right hands.',
    },
];

const steps = [
    {
        title: 'Connect Clockify',
        description:
            'Link your workspace with an API key. Credentials stay encrypted and server-side.',
    },
    {
        title: 'Import your history',
        description:
            'Evoriq synchronizes your existing time entries and keeps them up to date.',
    },
    {
        title: 'Explore the numbers',
        description:
            'Analyze, compare and export the story your tracked time has been telling.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;
    const appName = name ?? 'Evoriq';

    return (
        <>
            <Head title="Time analytics for Clockify" />

            <div className="flex min-h-svh flex-col bg-background">
                <header className="sticky top-0 z-50 border-b bg-background/80 backdrop-blur">
                    <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6">
                        <Link
                            href={home()}
                            className="flex items-center gap-2 font-semibold"
                        >
                            <AppLogoIcon className="size-7 fill-current text-foreground" />
                            <span>{appName}</span>
                        </Link>

                        <div className="flex items-center gap-2">
                            <ThemeToggle />
                            <Button asChild size="sm">
                                <Link href={auth.user ? dashboard() : login()}>
                                    {auth.user ? 'Dashboard' : 'Log in'}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </header>

                <main className="flex-1">
                    <section className="relative overflow-hidden">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-40 left-1/2 -z-10 size-[36rem] -translate-x-1/2 rounded-full bg-primary/5 blur-3xl"
                        />

                        <div className="mx-auto w-full max-w-6xl px-4 py-20 text-center sm:px-6 lg:py-28">
                            <div className="flex justify-center">
                                <Badge variant="outline">
                                    Historical time analytics for Clockify
                                </Badge>
                            </div>

                            <h1 className="mx-auto mt-6 max-w-3xl text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                Understand where your team&apos;s time really
                                goes.
                            </h1>

                            <p className="mx-auto mt-6 max-w-2xl text-lg text-pretty text-muted-foreground">
                                Evoriq turns your Clockify data into durable,
                                reportable history — so you can analyze trends,
                                compare periods and export the answers with
                                confidence.
                            </p>

                            <div className="mt-10 flex flex-wrap items-center justify-center gap-3">
                                <Button asChild size="lg">
                                    <Link
                                        href={auth.user ? dashboard() : login()}
                                    >
                                        {auth.user
                                            ? 'Go to dashboard'
                                            : 'Log in to Evoriq'}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto w-full max-w-6xl px-4 pb-20 sm:px-6 lg:pb-28">
                        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="rounded-xl border bg-card p-6 text-card-foreground shadow-sm transition-shadow hover:shadow-md"
                                >
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <feature.icon className="size-5" />
                                    </div>
                                    <h2 className="mt-4 font-medium">
                                        {feature.title}
                                    </h2>
                                    <p className="mt-1.5 text-sm text-muted-foreground">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="border-t bg-muted/30">
                        <div className="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 lg:py-24">
                            <div className="max-w-2xl">
                                <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                    From tracked time to real answers
                                </h2>
                                <p className="mt-3 text-muted-foreground">
                                    Three steps stand between you and a complete
                                    picture of your tracked work.
                                </p>
                            </div>

                            <ol className="mt-12 grid gap-8 md:grid-cols-3">
                                {steps.map((step, index) => (
                                    <li key={step.title}>
                                        <div className="flex size-9 items-center justify-center rounded-full border bg-background font-medium">
                                            {index + 1}
                                        </div>
                                        <h3 className="mt-4 font-medium">
                                            {step.title}
                                        </h3>
                                        <p className="mt-1.5 text-sm text-muted-foreground">
                                            {step.description}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex w-full max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:px-6">
                        <div className="flex items-center gap-2">
                            <AppLogoIcon className="size-5 fill-current" />
                            <span>{appName}</span>
                        </div>
                        <p>
                            &copy; {new Date().getFullYear()} {appName}. Built
                            on your own data.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
