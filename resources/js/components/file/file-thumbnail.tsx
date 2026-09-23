import { useState } from 'react';
import { fileIcon, isImage } from '@/components/file/file-utils';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    mimeType: string | null;
    thumbUrl: string | null;
    url: string | null;
    className?: string;
    iconClassName?: string;
};

export function FileThumbnail({
    name,
    mimeType,
    thumbUrl,
    url,
    className,
    iconClassName,
}: Props) {
    const [failed, setFailed] = useState(false);
    const Icon = fileIcon(mimeType);
    const source = thumbUrl ?? url;

    if (isImage(mimeType) && source && !failed) {
        return (
            <img
                src={source}
                alt={name}
                loading="lazy"
                onError={() => setFailed(true)}
                className={cn('size-full object-cover', className)}
            />
        );
    }

    return (
        <div
            className={cn(
                'flex size-full items-center justify-center',
                className,
            )}
        >
            <Icon
                className={cn('size-10 text-muted-foreground', iconClassName)}
            />
        </div>
    );
}
