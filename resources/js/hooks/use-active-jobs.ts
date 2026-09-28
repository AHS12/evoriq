import { usePage } from '@inertiajs/react';
import { useCan } from '@/hooks/use-can';
import { useLivePoll } from '@/hooks/use-live-poll';
import { index } from '@/routes/activity';

/** The component the live badge shares its poll with. */
const ACTIVITY_PAGE = 'data-processing/index';

/**
 * The number of active (pending + processing) jobs, shared by the server for
 * the sidebar badge. Returns 0 when the user has no access.
 *
 * On the Job activity page it joins the shared live-poll entry, so the page and
 * the badge cost one request per interval. Global live status (on every page)
 * is PIPE-08's concern.
 */
export function useActiveJobs(): number {
    const page = usePage();
    const can = useCan();

    const allowed =
        can('data-processing.view') || can('data-processing.view.all');
    const onActivityPage = page.component === ACTIVITY_PAGE;
    const count = page.props.activeJobs ?? 0;
    const live = allowed && onActivityPage;

    useLivePoll({
        url: index.url(),
        only: ['activeJobs', 'pipeline_revision'],
        enabled: live && count > 0,
        idleInterval: live ? 15000 : 0,
    });

    return allowed ? count : 0;
}
