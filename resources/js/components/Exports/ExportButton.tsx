import type { UseMutationResult } from '@tanstack/react-query';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { EXPORT_FILE_DAYS, EXPORT_LINK_LIFETIME } from '@/constants/exports';
import { useAuthStore } from '@/stores/useAuthStore';
import type { ExportQueued } from '@/types/exports';

interface ExportButtonProps {
    /** The button's words, for example "Export list". */
    label: string;
    /** What the file will hold, for the question, for example "everyone the current filters find, as a spreadsheet (CSV)". */
    contents: string;
    /** Asking for the export. The page supplies it with the filters now on screen. */
    request: UseMutationResult<ExportQueued, Error, void>;
}

/**
 * A button that asks for a CSV export. A file of people's private details is leaving the system, so it asks first and says
 * where the link goes, how long it works and when the file is deleted. The server builds the file and emails the link, so
 * nothing on the page changes, and the button stays off while the request is on its way so it cannot be asked for twice; the server's own words (including "You can export at most 5 times per hour.") are shown.
 */
export function ExportButton({ label, contents, request }: ExportButtonProps) {
    const [asking, setAsking] = useState(false);
    const email = useAuthStore((state) => state.user?.email);

    const confirm = () => {
        request.mutate(undefined, {
            onSuccess: (queued) => {
                toast.success(queued.message);
                setAsking(false);
            },
            onError: (error) => {
                toast.error(error.message);
                setAsking(false);
            },
        });
    };

    return (
        <>
            <Button type="button" variant="outline" size="lg" disabled={request.isPending} onClick={() => setAsking(true)}>
                {label}
            </Button>
            <ConfirmDialog
                open={asking}
                onOpenChange={setAsking}
                title={`${label}?`}
                description={`This asks for ${contents}. We will email the download link to ${email ?? 'you'}. The link works for ${EXPORT_LINK_LIFETIME} and the file is deleted after ${EXPORT_FILE_DAYS} days. Keep it safe: it holds your members' private details.`}
                confirmLabel="Email me the link"
                pending={request.isPending}
                onConfirm={confirm}
            />
        </>
    );
}
