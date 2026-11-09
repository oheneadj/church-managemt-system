import { Package } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { SmsPackageDialog } from '@/components/Platform/SmsPackageDialog';
import { SmsPackageListItem } from '@/components/Platform/SmsPackageListItem';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import { useSmsPackageMutations, useSmsPackages } from '@/hooks/usePlatform';
import type { SmsPackage } from '@/types/platform';

/** How many placeholder rows to show while the packages load. */
const LOADING_ROWS = 3;

/**
 * The SMS credit packages churches can buy (D-069, story 5.4): the platform owner adds one, changes its name, credits, price
 * and place in the list, and switches it off or on. Switching off asks first, and only stops a package being offered: nothing
 * already bought changes. There is no delete.
 */
export function PlatformPackagesPage() {
    const packages = useSmsPackages();
    const { setActive } = useSmsPackageMutations();
    const [adding, setAdding] = useState(false);
    const [changing, setChanging] = useState<SmsPackage | undefined>(undefined);
    const [switchingOff, setSwitchingOff] = useState<SmsPackage | undefined>(undefined);

    const switchTo = (one: SmsPackage, isActive: boolean, done: string) => {
        setActive.mutate(
            { id: one.id, isActive },
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

    const toggle = (one: SmsPackage) => (one.is_active ? setSwitchingOff(one) : switchTo(one, true, `${one.name} is on sale again.`));

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader
                title="SMS packages"
                description="The credit bundles churches can buy"
                actions={<Button onClick={() => setAdding(true)}>Add a package</Button>}
            />

            {packages.isPending && (
                <div aria-busy="true" className="flex flex-col gap-2">
                    {Array.from({ length: LOADING_ROWS }, (_, row) => (
                        <Skeleton key={row} className="h-[68px] w-full rounded-xl" />
                    ))}
                </div>
            )}

            {packages.isError && <ErrorNotice message={packages.error.message} onRetry={() => void packages.refetch()} />}

            {packages.data && packages.data.length === 0 && (
                <EmptyState icon={Package} title="No packages yet" description="Add the first package so churches can buy SMS credits." />
            )}

            {packages.data && packages.data.length > 0 && (
                <ul className="flex flex-col gap-2" aria-live="polite">
                    {packages.data.map((one) => (
                        <SmsPackageListItem key={one.id} smsPackage={one} onChange={setChanging} onToggleActive={toggle} />
                    ))}
                </ul>
            )}

            {adding && <SmsPackageDialog packages={packages.data ?? []} onClose={() => setAdding(false)} />}
            {changing !== undefined && (
                <SmsPackageDialog smsPackage={changing} packages={packages.data ?? []} onClose={() => setChanging(undefined)} />
            )}
            <ConfirmDialog
                open={switchingOff !== undefined}
                onOpenChange={(open) => !open && setSwitchingOff(undefined)}
                title={`Switch off ${switchingOff?.name ?? ''}?`}
                description="Churches will no longer be able to buy it. Credits already bought stay with the church. You can switch it on again."
                confirmLabel="Switch off"
                pending={setActive.isPending}
                onConfirm={() => switchingOff !== undefined && switchTo(switchingOff, false, `${switchingOff.name} is switched off.`)}
            />
        </div>
    );
}
