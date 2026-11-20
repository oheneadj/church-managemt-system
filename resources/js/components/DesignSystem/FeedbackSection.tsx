import { InboxIcon } from 'lucide-react';
import { toast } from 'sonner';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';

/** How long the pretend background job runs, so the progress toast can be seen turning into a result. */
const PROGRESS_MS = 2500;

function showProgressToast() {
    const job = new Promise<void>((resolve) => setTimeout(resolve, PROGRESS_MS));

    toast.promise(job, {
        loading: 'Your report is being prepared…',
        success: 'Your report is ready.',
        error: 'We could not prepare your report. Try again.',
    });
}

/**
 * Feedback: the five kinds of toast, an inline error with a way to retry, and an empty state that says what to do next.
 */
export function FeedbackSection() {
    return (
        <ShowcaseSection
            id="feedback"
            title="Feedback"
            description="Short, calm messages in one place. Errors stay until dismissed and offer a way forward."
        >
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={() => toast.success('Changes saved.')}>
                    Success toast
                </Button>
                <Button variant="outline" onClick={() => toast.info('Your report is being prepared.')}>
                    Information toast
                </Button>
                <Button variant="outline" onClick={() => toast.warning('You have unsaved changes.')}>
                    Warning toast
                </Button>
                <Button
                    variant="outline"
                    onClick={() =>
                        toast.error('We could not save your changes. Try again.', {
                            duration: Infinity,
                            closeButton: true,
                            action: { label: 'Try again', onClick: () => toast.success('Changes saved.') },
                        })
                    }
                >
                    Error toast
                </Button>
                <Button variant="outline" onClick={showProgressToast}>
                    Progress toast
                </Button>
            </div>
            <ErrorNotice
                message="We could not load this. Check your connection and try again."
                onRetry={() => toast.info('Trying again…')}
            />
            <EmptyState
                icon={InboxIcon}
                title="Nothing here yet"
                description="When there is something to show, it appears here. Add the first item to get started."
                action={<Button onClick={() => toast.info('Add item')}>Add item</Button>}
            />
        </ShowcaseSection>
    );
}
