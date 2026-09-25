import { router } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/locale';

export function LanguageSelect({ className }: { className?: string }) {
    const { locale, supported } = useTranslation();

    const switchTo = (code: string) => {
        if (code === locale) {
            return;
        }

        // A full reload re-fetches the HTML (fresh <html lang>), the
        // dictionary and all Intl formatting — a soft visit would leave the
        // document language stale.
        router.post(
            update.url(),
            { locale: code },
            {
                preserveScroll: true,
                onFinish: () => window.location.reload(),
            },
        );
    };

    return (
        <Select value={locale} onValueChange={switchTo}>
            <SelectTrigger className={className} aria-label="Language">
                <SelectValue />
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
