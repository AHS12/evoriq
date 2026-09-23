import type { ReactNode } from 'react';
import { useCan } from '@/hooks/use-can';

type Props = {
    permission: string;
    children: ReactNode;
    fallback?: ReactNode;
};

export function Can({ permission, children, fallback = null }: Props) {
    const can = useCan();

    return <>{can(permission) ? children : fallback}</>;
}
