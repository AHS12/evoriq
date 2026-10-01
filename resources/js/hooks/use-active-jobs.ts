import { usePipelineStatus } from '@/hooks/use-pipeline-status';

/**
 * The number of active (pending + processing) jobs the user can see, shared by
 * the server for the sidebar badge. Now sourced from the global pipeline status
 * (PIPE-08) so the badge, header indicator and command palette share one poll.
 */
export function useActiveJobs(): number {
    return usePipelineStatus().status.active_count;
}
