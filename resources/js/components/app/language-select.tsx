import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cancelPendingAppearancePersist } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/locale';

export function LanguageSelect({ className }: { className?: string }) {
    const { locale, supported } = useTranslation();
    const [switching, setSwitching] = useState(false);

    const switchTo = (code: string) => {
        if (code === locale || switching) {
            return;
        }

        // A debounced appearance PATCH would cancel this visit before
        // onFinish runs, leaving the page un-reloaded on the old locale.
        cancelPendingAppearancePersist();
        setSwitching(true);

        // A full reload re-fetches the HTML (fresh <html lang>), the
        // dictionary and all Intl formatting — a soft visit would leave the
        // document language stale.
        router.post(
            update.url(),
            { locale: code },
            {
                preserveScroll: true,
                onSuccess: () => window.location.reload(),
                onFinish: () => window.location.reload(),
                onError: () => setSwitching(false),
            },
        );
    };

    const current = supported.find((item) => item.code === locale);

    return (
        <Select value={locale} onValueChange={switchTo} disabled={switching}>
            <SelectTrigger className={className} aria-label="Language">
                <SelectValue>{current?.name ?? locale}</SelectValue>
            </SelectTrigger>
            <SelectContent>
                {supported.map(({ code, name }) => (
                    <SelectItem key={code} value={code}>
                        {name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
