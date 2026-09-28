import {
    FileArchive,
    FileAudio,
    FileImage,
    FileSpreadsheet,
    FileText,
    FileVideo,
    File as FileIcon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { formatBytes } from '@/lib/format';

export function formatSize(bytes: number | null): string {
    if (bytes === null) {
        return '—';
    }

    return formatBytes(bytes);
}

export function isImage(mime: string | null): boolean {
    return mime !== null && mime.startsWith('image/');
}

export function isPdf(mime: string | null): boolean {
    return mime === 'application/pdf';
}

export function fileIcon(mime: string | null): LucideIcon {
    const value = mime ?? '';

    if (value.startsWith('image/')) {
        return FileImage;
    }

    if (value.startsWith('video/')) {
        return FileVideo;
    }

    if (value.startsWith('audio/')) {
        return FileAudio;
    }

    if (
        value.includes('spreadsheet') ||
        value.includes('excel') ||
        value.includes('csv')
    ) {
        return FileSpreadsheet;
    }

    if (
        value.includes('zip') ||
        value.includes('compressed') ||
        value.includes('tar')
    ) {
        return FileArchive;
    }

    if (
        value.startsWith('text/') ||
        value.includes('pdf') ||
        value.includes('word') ||
        value.includes('document')
    ) {
        return FileText;
    }

    return FileIcon;
}
