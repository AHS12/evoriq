import { router } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/locale';
import { cn } from '@/lib/utils';

export function LanguageToggle() {
    const { t, locale, supported } = useTranslation();

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
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    aria-label={t('Change language')}
                >
                    <Languages className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40">
                {supported.map(({ code, name }) => (
                    <DropdownMenuItem
                        key={code}
                        onClick={() => switchTo(code)}
                        className={cn(
                            'cursor-pointer',
                            code === locale && 'bg-accent',
                        )}
                    >
                        <Check
                            className={cn(
                                'mr-2 size-4',
                                code === locale ? 'opacity-100' : 'opacity-0',
                            )}
                        />
                        {name}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
