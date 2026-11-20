import { StatCard } from '@/components/Charts/StatCard';
import type { GivingSummary } from '@/types/members';

interface MemberGivingTabProps {
    /** Missing when the signed-in user's role may not see giving. */
    summary: GivingSummary | undefined;
}

/**
 * What the member has given this month, this year and in total, as stat cards. Roles without giving access see a plain
 * message instead; the amounts are the texts the server formatted and are never calculated in the browser.
 */
export function MemberGivingTab({ summary }: MemberGivingTabProps) {
    if (summary === undefined) {
        return <p className="text-body text-muted-foreground">Your role does not include giving details.</p>;
    }

    const totals = [
        { label: 'This month', formatted: summary.this_month_formatted },
        { label: 'This year', formatted: summary.this_year_formatted },
        { label: 'All time', formatted: summary.all_time_formatted },
    ];

    return (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
            {totals.map((total) => (
                <StatCard key={total.label} label={total.label} value={total.formatted} />
            ))}
        </div>
    );
}
