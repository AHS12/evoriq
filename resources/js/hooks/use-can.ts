import { usePage } from '@inertiajs/react';

export function useCan() {
    const { auth } = usePage().props;

    const permissions = auth?.permissions ?? [];

    return (permission: string): boolean => permissions.includes(permission);
}
