import { usePage } from '@inertiajs/react';

/**
 * The number of active (pending + processing) jobs, shared by the server for
 * the sidebar badge. Returns 0 when the user has no access.
 */
export function useActiveJobs(): number {
    return usePage().props.activeJobs ?? 0;
}
