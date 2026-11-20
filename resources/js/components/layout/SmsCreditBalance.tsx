import { useState } from 'react';
import { TopUpDialog } from '@/components/layout/TopUpDialog';
import { Button } from '@/components/ui/button';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { LOW_SMS_CREDITS } from '@/constants/credits';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useSmsCredits } from '@/hooks/useSmsCredits';
import { formatCount } from '@/utils/formatCount';

/**
 * The church's SMS credit balance for the header. Everyone who is signed in sees it, because everyone's work sends texts
 * (receipts, check-ins). At or under the low-credit limit it says so in words, and at none it says what stops. Only staff
 * who manage the church get the Top up button.
 */
export function SmsCreditBalance() {
    const credits = useSmsCredits();
    const canTopUp = useCan(PERMISSIONS.CHURCH_SETTINGS);
    const [topUpOpen, setTopUpOpen] = useState(false);
    const empty = credits <= 0;
    const low = credits <= LOW_SMS_CREDITS;

    return (
        <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-body-sm">
            <span className="text-muted-foreground">SMS credits</span>
            <span className="font-medium tabular-nums">{formatCount(credits)}</span>
            {low && (
                <span role="status">
                    <StatusBadge label={empty ? 'None left' : 'Running low'} tone={empty ? 'danger' : 'warning'} />
                </span>
            )}
            {empty && <span className="text-muted-foreground">Gift receipts and other texts are not sent.</span>}
            {canTopUp && (
                <Button type="button" variant="outline" size="sm" onClick={() => setTopUpOpen(true)}>
                    Top up
                </Button>
            )}
            <TopUpDialog open={topUpOpen} onOpenChange={setTopUpOpen} />
        </div>
    );
}
