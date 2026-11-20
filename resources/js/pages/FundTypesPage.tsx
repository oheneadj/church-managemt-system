import { Coins } from 'lucide-react';
import { useState } from 'react';
import { Link } from 'react-router';
import { toast } from 'sonner';
import { FundTypeDialog } from '@/components/FundTypes/FundTypeDialog';
import { FundTypeListItem } from '@/components/FundTypes/FundTypeListItem';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import { ROUTES } from '@/constants/routes';
import { useAllFundTypes, useFundTypeMutations } from '@/hooks/useFundTypes';
import type { FundType } from '@/types/giving';

/** How many placeholder rows to show while the funds load. */
const LOADING_ROWS = 4;

/**
 * The funds gifts can be given to (D-046 point 4): add one, change its name, description and place in the list, and switch
 * it off or on. Only the church admin may open it (`fund-types.manage`; the server answers 403 to anyone else and the Settings
 * page only offers the link to them). Switching off asks first; switching on does not. Past gifts keep pointing at a fund that
 * is switched off, and it only stops being offered for new gifts and pledges.
 */
export function FundTypesPage() {
    const funds = useAllFundTypes();
    const { setActive } = useFundTypeMutations();
    const [adding, setAdding] = useState(false);
    const [changing, setChanging] = useState<FundType | undefined>(undefined);
    const [switchingOff, setSwitchingOff] = useState<FundType | undefined>(undefined);

    const switchTo = (fund: FundType, isActive: boolean, done: string) => {
        setActive.mutate(
            { id: fund.id, isActive },
            {
                onSuccess: () => {
                    toast.success(done);
                    setSwitchingOff(undefined);
                },
                onError: (error) => {
                    toast.error(error.message);
                    setSwitchingOff(undefined);
                },
            },
        );
    };

    const toggle = (fund: FundType) => {
        if (fund.is_active) {
            setSwitchingOff(fund);
        } else {
            switchTo(fund, true, `${fund.name} is in use again.`);
        }
    };

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="Giving funds"
                description="What gifts can be given for"
                actions={<Button onClick={() => setAdding(true)}>Add a fund</Button>}
            />
            <Link to={ROUTES.SETTINGS} className="self-start text-sm underline underline-offset-4">
                Back to settings
            </Link>

            {funds.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[68px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {funds.isError && <ErrorNotice message={funds.error.message} onRetry={() => void funds.refetch()} />}

            {funds.data && funds.data.length === 0 && (
                <EmptyState icon={Coins} title="No funds yet" description="Add the first fund so gifts can be recorded against it." />
            )}

            {funds.data && funds.data.length > 0 && (
                <ul className="flex flex-col gap-2" aria-live="polite">
                    {funds.data.map((fund) => (
                        <FundTypeListItem key={fund.id} fund={fund} onChange={setChanging} onToggleActive={toggle} />
                    ))}
                </ul>
            )}

            {/* Mounted only while open, so each opening starts blank or from the fund chosen. */}
            {adding && <FundTypeDialog funds={funds.data ?? []} onClose={() => setAdding(false)} />}
            {changing !== undefined && <FundTypeDialog fund={changing} funds={funds.data ?? []} onClose={() => setChanging(undefined)} />}
            <ConfirmDialog
                open={switchingOff !== undefined}
                onOpenChange={(open) => !open && setSwitchingOff(undefined)}
                title={`Switch off ${switchingOff?.name ?? ''}?`}
                description="It will no longer be offered when a gift or a pledge is recorded. Gifts already given to it stay on record and in the reports. You can switch it on again."
                confirmLabel="Switch off"
                destructive
                pending={setActive.isPending}
                onConfirm={() => switchingOff !== undefined && switchTo(switchingOff, false, `${switchingOff.name} is switched off.`)}
            />
        </div>
    );
}
