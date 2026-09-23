import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AdminStep } from '@/components/setup/admin-step';
import { DatabaseStep } from '@/components/setup/database-step';
import { DriversStep } from '@/components/setup/drivers-step';
import { FinishStep } from '@/components/setup/finish-step';
import { MigrationStep } from '@/components/setup/migration-step';
import { RequirementsList } from '@/components/setup/requirements-list';
import { SetupStepper } from '@/components/setup/setup-stepper';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useZodForm } from '@/hooks/use-zod-form';
import { createAdminSchema } from '@/lib/schemas/setup';
import { environment as generateKeyRoute, store } from '@/routes/setup';
import type {
    AccountField,
    AccountFormData,
    DatabaseDefaults,
    DatabaseDriverOption,
    DriverDefaults,
    DriverOptions,
    ExistingAdmin,
    RecommendedDrivers,
    RedisStatus,
    Requirement,
    SetupStatus,
} from '@/types/setup';

type Step = 'welcome' | 'database' | 'migrate' | 'drivers' | 'admin' | 'finish';

type Props = {
    requirements: Requirement[];
    status: SetupStatus;
    hasSuperAdmin: boolean;
    existingAdmin: ExistingAdmin | null;
    defaults: {
        appName: string;
    };
    database: {
        drivers: DatabaseDriverOption[];
        current: DatabaseDefaults;
    };
    drivers: {
        options: DriverOptions;
        current: DriverDefaults;
        redis: RedisStatus;
        recommended: RecommendedDrivers;
    };
};

export default function Setup({
    requirements,
    status,
    hasSuperAdmin,
    existingAdmin,
    defaults,
    database,
    drivers,
}: Props) {
    const [step, setStep] = useState<Step>(() =>
        resolveInitialStep(requirements, status, hasSuperAdmin),
    );
    const [generatingKey, setGeneratingKey] = useState(false);

    const stepOrder: Step[] = hasSuperAdmin
        ? ['welcome', 'database', 'migrate', 'drivers', 'finish']
        : ['welcome', 'database', 'migrate', 'drivers', 'admin', 'finish'];

    const currentIndex = Math.max(0, stepOrder.indexOf(step));
    const [maxReached, setMaxReached] = useState(currentIndex);

    useEffect(() => {
        setMaxReached((previous) => Math.max(previous, currentIndex));
    }, [currentIndex]);

    const { form, errors, validate, setField } = useZodForm<AccountFormData>(
        {
            app_name: defaults.appName,
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        },
        createAdminSchema(!hasSuperAdmin),
    );

    const requirementsMet = requirements
        .filter((requirement) => requirement.required)
        .every((requirement) => requirement.passed);

    const stepLabels = hasSuperAdmin
        ? ['Welcome', 'Database', 'Migrate', 'Drivers', 'Finish']
        : [
              'Welcome',
              'Database',
              'Migrate',
              'Drivers',
              'Administrator',
              'Finish',
          ];

    const navigateTo = (index: number) => {
        const target = stepOrder[index];

        if (target) {
            setStep(target);
        }
    };

    const driverDefaults: DriverDefaults = {
        session: drivers.recommended.session,
        cache: drivers.recommended.cache,
        queue: drivers.recommended.queue,
        redisHost: drivers.current.redisHost,
        redisPort: drivers.current.redisPort,
        redisPassword: '',
    };

    const generateKey = () => {
        setGeneratingKey(true);

        router.post(
            generateKeyRoute.url(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setGeneratingKey(false),
            },
        );
    };

    const complete = () => {
        if (!validate()) {
            return;
        }

        form.post(store.url());
    };

    const handleAccountChange = (field: AccountField, value: string) => {
        setField(field, value);
    };

    return (
        <>
            <Head title="Setup" />

            <div className="grid min-h-svh lg:grid-cols-2">
                <aside className="relative hidden flex-col justify-between overflow-hidden bg-primary p-10 text-primary-foreground lg:flex">
                    <div className="relative z-10 text-lg font-semibold">
                        Evoriq
                    </div>
                    <div className="relative z-10 space-y-3">
                        <h1 className="text-2xl font-semibold">
                            Set up your workspace
                        </h1>
                        <p className="max-w-sm text-sm text-primary-foreground/70">
                            Historical time analytics and reporting for
                            Clockify. This one-time setup configures your
                            database, runtime drivers and administrator access.
                        </p>
                    </div>
                    <div className="relative z-10 text-xs text-primary-foreground/50">
                        © {new Date().getFullYear()} Evoriq
                    </div>
                </aside>

                <main className="flex items-center justify-center p-6 md:p-10">
                    <div className="w-full max-w-lg space-y-8">
                        <SetupStepper
                            steps={stepLabels}
                            current={currentIndex}
                            reachable={maxReached}
                            onNavigate={navigateTo}
                        />

                        {step === 'welcome' && (
                            <div className="space-y-4">
                                <div className="space-y-1">
                                    <h2 className="text-xl font-semibold">
                                        Before we begin
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        We checked your environment. Resolve any
                                        failed checks to continue.
                                    </p>
                                </div>

                                <RequirementsList requirements={requirements} />

                                {!status.environment.key_set && (
                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3 text-sm">
                                        <span>
                                            An application key is required to
                                            encrypt sessions.
                                        </span>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={generateKey}
                                            disabled={generatingKey}
                                        >
                                            {generatingKey && <Spinner />}
                                            Generate key
                                        </Button>
                                    </div>
                                )}

                                <div className="flex justify-end">
                                    <Button
                                        onClick={() =>
                                            setStep(
                                                resolveNextFromWelcome(
                                                    status,
                                                    hasSuperAdmin,
                                                ),
                                            )
                                        }
                                        disabled={
                                            !requirementsMet ||
                                            !status.environment.key_set
                                        }
                                    >
                                        Continue
                                    </Button>
                                </div>
                            </div>
                        )}

                        {step === 'database' && (
                            <DatabaseStep
                                drivers={database.drivers}
                                defaults={database.current}
                                onComplete={() => setStep('migrate')}
                            />
                        )}

                        {step === 'migrate' && (
                            <MigrationStep
                                migrated={status.database.migrated}
                                seeded={status.database.seeded}
                                onComplete={() => setStep('drivers')}
                            />
                        )}

                        {step === 'drivers' && (
                            <DriversStep
                                options={drivers.options}
                                defaults={driverDefaults}
                                redis={drivers.redis}
                                onComplete={() =>
                                    setStep(hasSuperAdmin ? 'finish' : 'admin')
                                }
                            />
                        )}

                        {step === 'admin' && (
                            <AdminStep
                                data={form.data}
                                errors={errors}
                                processing={form.processing}
                                onChange={handleAccountChange}
                                onBack={() => setStep('drivers')}
                                onNext={() => setStep('finish')}
                            />
                        )}

                        {step === 'finish' && (
                            <FinishStep
                                data={form.data}
                                errors={errors}
                                processing={form.processing}
                                hasSuperAdmin={hasSuperAdmin}
                                existingAdmin={existingAdmin}
                                onAppNameChange={(value) =>
                                    handleAccountChange('app_name', value)
                                }
                                onBack={() =>
                                    setStep(hasSuperAdmin ? 'drivers' : 'admin')
                                }
                                onSubmit={complete}
                            />
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}

function resolveInitialStep(
    requirements: Requirement[],
    status: SetupStatus,
    hasSuperAdmin: boolean,
): Step {
    const requirementsMet = requirements
        .filter((requirement) => requirement.required)
        .every((requirement) => requirement.passed);

    if (!requirementsMet || !status.environment.key_set) {
        return 'welcome';
    }

    return resolveNextFromWelcome(status, hasSuperAdmin);
}

function resolveNextFromWelcome(
    status: SetupStatus,
    hasSuperAdmin: boolean,
): Step {
    if (!status.database.configured) {
        return 'database';
    }

    if (!status.database.migrated) {
        return 'migrate';
    }

    return hasSuperAdmin ? 'finish' : 'admin';
}
