import { toast as sonner } from 'sonner';

export type ToastTone = 'success' | 'info' | 'warning' | 'error';

export type ToastAction = {
    label: string;
    onClick: () => void;
};

export type ToastOptions = {
    /** Supporting line rendered under the title. */
    description?: string;
    /**
     * Stable identity for dedupe: a later toast with the same id replaces the
     * earlier one instead of stacking. Defaults to `{tone}:{message}`.
     */
    id?: string;
    /** Override the per-tone default duration (ms). `Infinity` keeps it open. */
    duration?: number;
    /** Optional single action button. */
    action?: ToastAction;
};

/**
 * Per-tone defaults: brief confirmations for success/info, longer windows for
 * anything the user may need to read or act on.
 */
const DURATIONS: Record<ToastTone, number> = {
    success: 4000,
    info: 4000,
    warning: 6000,
    error: 8000,
};

function show(
    tone: ToastTone,
    message: string,
    options: ToastOptions = {},
): string | number {
    const payload = {
        description: options.description,
        id: options.id ?? `${tone}:${message}`,
        duration: options.duration ?? DURATIONS[tone],
        action: options.action,
    };

    switch (tone) {
        case 'success':
            return sonner.success(message, payload);
        case 'warning':
            return sonner.warning(message, payload);
        case 'error':
            return sonner.error(message, payload);
        case 'info':
        default:
            return sonner.info(message, payload);
    }
}

/**
 * The single toast entry point. Always use this instead of importing `sonner`
 * directly so tone, duration and dedupe stay consistent across the app.
 */
export const toast = {
    success: (message: string, options?: ToastOptions) =>
        show('success', message, options),
    info: (message: string, options?: ToastOptions) =>
        show('info', message, options),
    warning: (message: string, options?: ToastOptions) =>
        show('warning', message, options),
    error: (message: string, options?: ToastOptions) =>
        show('error', message, options),
    dismiss: (id?: string | number) => sonner.dismiss(id),
};
