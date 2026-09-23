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

export function formatSize(bytes: number | null): string {
    if (bytes === null || bytes === 0) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
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
