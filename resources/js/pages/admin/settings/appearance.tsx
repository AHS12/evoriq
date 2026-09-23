import { Head } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import AppearanceTabs from '@/components/appearance-tabs';
import { AccentPicker } from '@/components/appearance/accent-picker';
import { ContrastToggle } from '@/components/appearance/contrast-toggle';
import { ThemePicker } from '@/components/appearance/theme-picker';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DEFAULT_APPEARANCE,
    useAppearancePreferences,
} from '@/hooks/use-appearance';

export default function AppearanceSettings() {
    const { update } = useAppearancePreferences();

    return (
        <>
            <Head title="Appearance settings" />

            <Card>
                <CardHeader>
                    <CardTitle>Appearance</CardTitle>
                    <CardDescription>
                        Choose how Evoriq looks. Your preferences are saved to
                        your account and follow you across devices.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <div className="space-y-2">
                        <p className="text-sm font-medium">Mode</p>
                        <AppearanceTabs />
                    </div>

                    <div className="space-y-2">
                        <p className="text-sm font-medium">Theme</p>
                        <ThemePicker />
                    </div>

                    <div className="space-y-2">
                        <p className="text-sm font-medium">Accent color</p>
                        <AccentPicker />
                    </div>

                    <ContrastToggle />

                    <div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => update({ ...DEFAULT_APPEARANCE })}
                        >
                            <RotateCcw className="size-4" />
                            Reset to defaults
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </>
    );
}
