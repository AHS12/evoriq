import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useAppearancePreferences } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';

export function ContrastToggle() {
    const { preferences, update } = useAppearancePreferences();
    const { t } = useTranslation();
    const high = preferences.contrast === 'high';

    return (
        <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
            <div>
                <Label htmlFor="high-contrast" className="font-medium">
                    {t('High contrast')}
                </Label>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'Increase contrast and strengthen borders for readability.',
                    )}
                </p>
            </div>
            <Switch
                id="high-contrast"
                checked={high}
                onCheckedChange={(checked) =>
                    update({ contrast: checked ? 'high' : 'normal' })
                }
            />
        </div>
    );
}
