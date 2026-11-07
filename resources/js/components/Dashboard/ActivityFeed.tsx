import { History } from 'lucide-react';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { DASHBOARD_ACTIVITY_COUNT } from '@/constants/dashboard';
import { useRecentActivity } from '@/hooks/useActivity';
import { formatDateTime } from '@/utils/formatDateTime';

/** How many placeholder rows to show while the feed loads. */
const LOADING_ROWS = 4;

/**
 * What has happened lately in the church, in plain sentences the server wrote: members added, gifts recorded and so on. The
 * server decides which lines this person may see (a pastor sees no gifts), so the page only shows what it is given. It is for
 * staff who may read reports, and has its own loading, empty and failure states so it never holds back the cards above it.
 */
export function ActivityFeed() {
    const activity = useRecentActivity(DASHBOARD_ACTIVITY_COUNT);

    return (
        <section aria-labelledby="activity-heading" className="flex flex-col gap-2">
            <h2 id="activity-heading" className="font-medium">
                Recent activity
            </h2>

            {activity.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[52px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {activity.isError && <ErrorNotice message={activity.error.message} onRetry={() => void activity.refetch()} />}

            {activity.data && activity.data.data.length === 0 && (
                <EmptyState
                    icon={History}
                    title="Nothing yet"
                    description="What happens in your church, such as members added and gifts recorded, will appear here."
                />
            )}

            {activity.data && activity.data.data.length > 0 && (
                <ul className="flex flex-col gap-2">
                    {activity.data.data.map((line) => (
                        <li key={line.id} className="rounded-xl border p-3">
                            <span className="block text-sm">{line.text}</span>
                            <span className="block text-xs text-muted-foreground">
                                {[line.actor_name, line.occurred_at === null ? null : formatDateTime(line.occurred_at)]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
