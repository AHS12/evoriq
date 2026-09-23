import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

type Props = {
    value: string;
    label?: string;
    className?: string;
};

export function CopyButton({ value, label = 'Copy', className }: Props) {
    const [copiedText, copy] = useClipboard();
    const copied = copiedText === value;

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            className={className}
            onClick={() => void copy(value)}
        >
            {copied ? <Check /> : <Copy />}
            {copied ? 'Copied' : label}
        </Button>
    );
}
