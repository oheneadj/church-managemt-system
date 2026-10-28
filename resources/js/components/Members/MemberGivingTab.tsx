import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import type { GivingSummary } from '@/types/members';

interface MemberGivingTabProps {
    /** Missing when the signed-in user's role may not see giving. */
    summary: GivingSummary | undefined;
}

/**
 * What the member has given this year, this month and in total. Roles without giving access see a plain
 * message instead; the amounts are never calculated in the browser.
 */
export function MemberGivingTab({ summary }: MemberGivingTabProps) {
    if (summary === undefined) {
        return <p className="text-sm text-muted-foreground">Your role does not include giving details.</p>;
    }

    const totals = [
        { label: 'This month', formatted: summary.this_month_formatted },
        { label: 'This year', formatted: summary.this_year_formatted },
        { label: 'All time', formatted: summary.all_time_formatted },
    ];

    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            {totals.map((total) => (
                <Card key={total.label} size="sm">
                    <CardHeader>
                        <CardTitle>{total.label}</CardTitle>
                    </CardHeader>
                    <CardContent className="text-lg font-semibold">
                        <MoneyDisplay formatted={total.formatted} />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
