import { Download, EyeOff } from 'lucide-react';
import {
    fileIcon,
    formatSize,
    isImage,
    isPdf,
} from '@/components/file/file-utils';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type PreviewFile = {
    name: string;
    mime_type: string | null;
    url: string | null;
    type_label?: string | null;
    size?: number | null;
};

type Props = {
    file: PreviewFile | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export function FilePreviewDialog({ file, open, onOpenChange }: Props) {
    if (!file) {
        return null;
    }

    const Icon = fileIcon(file.mime_type);
    const canPreviewImage = file.url !== null && isImage(file.mime_type);
    const canPreviewPdf = file.url !== null && isPdf(file.mime_type);
    const hasPreview = canPreviewImage || canPreviewPdf;

    const meta = [file.type_label, formatSize(file.size ?? null)]
        .filter((value) => value && value !== '—')
        .join(' · ');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={cn(hasPreview ? 'max-w-4xl' : 'max-w-md')}
            >
                <DialogHeader className="min-w-0">
                    <DialogTitle className="truncate pr-8">
                        {file.name}
                    </DialogTitle>
                    <DialogDescription className="truncate">
                        {meta || file.mime_type || 'File'}
                    </DialogDescription>
                </DialogHeader>

                {hasPreview ? (
                    <div className="flex items-center justify-center overflow-hidden rounded-md border bg-muted/30">
                        {canPreviewImage ? (
                            <img
                                src={file.url ?? undefined}
                                alt={file.name}
                                className="max-h-[60vh] object-contain"
                            />
                        ) : (
                            <iframe
                                src={file.url ?? undefined}
                                title={file.name}
                                className="h-[60vh] w-full"
                            />
                        )}
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed bg-muted/30 p-8 text-center">
                        <div className="flex size-14 items-center justify-center rounded-full bg-background">
                            <Icon className="size-7 text-muted-foreground" />
                        </div>
                        <div className="space-y-1">
                            <p className="flex items-center justify-center gap-1.5 text-sm font-medium">
                                <EyeOff className="size-4" />
                                No preview available
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Download this file to view its contents.
                            </p>
                        </div>
                    </div>
                )}

                {file.url && (
                    <DialogFooter>
                        <Button asChild variant="outline">
                            <a href={file.url} target="_blank" rel="noreferrer">
                                <Download className="size-4" />
                                Download
                            </a>
                        </Button>
                    </DialogFooter>
                )}
            </DialogContent>
        </Dialog>
    );
}
