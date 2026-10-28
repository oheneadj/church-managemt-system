import { useState } from 'react';
import { toast } from 'sonner';
import { EraseMemberDialog } from '@/components/Members/EraseMemberDialog';
import { Button } from '@/components/ui/button';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useMemberMutations } from '@/hooks/useMemberMutations';
import { downloadJson } from '@/utils/downloadJson';

interface MemberPrivacyActionsProps {
    memberId: string;
    memberNumber: string;
    fullName: string;
}

/**
 * Subject access and erasure for one member. Each button shows only to staff who hold its permission; the server
 * checks again. Nothing shows when the user holds neither.
 */
export function MemberPrivacyActions({ memberId, memberNumber, fullName }: MemberPrivacyActionsProps) {
    const canExport = useCan(PERMISSIONS.MEMBERS_EXPORT);
    const canErase = useCan(PERMISSIONS.MEMBERS_ERASE);
    const { exportData } = useMemberMutations();
    const [erasing, setErasing] = useState(false);

    if (!canExport && !canErase) {
        return null;
    }

    const download = () =>
        exportData.mutate(memberId, {
            onSuccess: (data) => downloadJson(`${memberNumber}-data.json`, data),
            onError: (error) => toast.error(error.message),
        });

    return (
        <section aria-labelledby="privacy-heading" className="flex flex-col gap-2 rounded-xl border p-4">
            <h2 id="privacy-heading" className="text-sm font-semibold">
                Personal data
            </h2>
            <div className="flex flex-wrap gap-2">
                {canExport && (
                    <Button type="button" variant="outline" onClick={download} disabled={exportData.isPending}>
                        {exportData.isPending ? 'Preparing…' : 'Download their data'}
                    </Button>
                )}
                {canErase && (
                    <Button type="button" variant="outline" onClick={() => setErasing(true)}>
                        Erase personal data
                    </Button>
                )}
            </div>
            {canErase && <EraseMemberDialog memberId={memberId} fullName={fullName} open={erasing} onOpenChange={setErasing} />}
        </section>
    );
}
