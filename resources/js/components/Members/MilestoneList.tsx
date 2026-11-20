import type { Milestone } from '@/types/members';
import { formatDate } from '@/utils/formatDate';

interface MilestoneListProps {
    milestones: readonly Milestone[];
}

/**
 * A member's milestones, oldest first, each with its date and who officiated.
 */
export function MilestoneList({ milestones }: MilestoneListProps) {
    if (milestones.length === 0) {
        return <p className="text-sm text-muted-foreground">No milestones recorded yet.</p>;
    }

    const oldestFirst = [...milestones].sort((a, b) => a.occurred_at.localeCompare(b.occurred_at));

    return (
        <ul className="flex flex-col gap-2">
            {oldestFirst.map((milestone) => (
                <li key={milestone.id} className="text-sm">
                    <span className="font-medium">{milestone.type_label}</span> · {formatDate(milestone.occurred_at)}
                    {milestone.officiant && <span className="text-muted-foreground"> · {milestone.officiant}</span>}
                    {milestone.cohort && <span className="text-muted-foreground"> · {milestone.cohort}</span>}
                    {milestone.notes && <p className="text-muted-foreground">{milestone.notes}</p>}
                </li>
            ))}
        </ul>
    );
}
