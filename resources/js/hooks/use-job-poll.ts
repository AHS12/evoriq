import { useLivePoll } from '@/hooks/use-live-poll';
import { index } from '@/routes/activity';

/**
 * Props the Job Center polls. Kept in one place so the page and the sidebar
 * badge resolve to the same shared registry entry (one request per interval).
 */
export const ACTIVITY_POLL_PROPS = [
    'jobs',
    'stats',
    'activeJobs',
    'activeRuns',
    'pipeline_revision',
];

/**
 * Keeps the Job Center fresh by polling the shared page props.
 *
 * A thin wrapper over the shared live-poll transport (PIPE-03): polling only
 * runs while there is active work, the tab is visible and the user has not
 * paused it. Returns a `live` flag plus pause/resume controls so the UI can
 * show a "Live / Paused" state.
 */
export function useJobPoll(active: boolean) {
    const { live, pause, resume } = useLivePoll({
        url: index.url(),
        only: ACTIVITY_POLL_PROPS,
        enabled: active,
        idleInterval: 15000,
    });

    return { live, pause, resume };
}
