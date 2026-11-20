import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { Link, useNavigate } from 'react-router';
import { toast } from 'sonner';
import { SelectField } from '@/components/form/SelectField';
import { TextAreaField } from '@/components/form/TextAreaField';
import { BroadcastEstimateNotice } from '@/components/Messages/BroadcastEstimateNotice';
import {
    blankBroadcastValues,
    broadcastFormSchema,
    toBroadcastInput,
    type BroadcastFormValues,
} from '@/components/Messages/broadcastFormSchema';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { PageHeader } from '@/components/ui/PageHeader';
import { MEMBER_STATUSES } from '@/constants/enums';
import { ESTIMATE_DELAY_MS } from '@/constants/messages';
import { ROUTES } from '@/constants/routes';
import { useBroadcastEstimate, useSendBroadcast } from '@/hooks/useBroadcast';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';
import { useDepartments } from '@/hooks/useDepartments';
import { newIdempotencyKey } from '@/lib/idempotencyKey';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { BroadcastInput } from '@/types/messages';
import { formatCount } from '@/utils/formatCount';

/**
 * Sends a text message to a group of members (story 5.11, SMS only until WhatsApp is approved). The cost is worked out by
 * the server as the message is typed, and sending asks first, because a text cannot be called back and uses credits. One key
 * is made for each time the page opens, so a double click or a retry sends one broadcast. Only staff who may send
 * messages can open it (the server answers 403 to anyone else, and the menu hides the link).
 */
export function BroadcastPage() {
    const navigate = useNavigate();
    const departments = useDepartments();
    const send = useSendBroadcast();
    const [idempotencyKey] = useState(newIdempotencyKey);
    const [confirming, setConfirming] = useState<BroadcastInput | undefined>(undefined);
    const form = useForm<BroadcastFormValues>({ resolver: zodResolver(broadcastFormSchema), defaultValues: blankBroadcastValues() });
    const { errors } = form.formState;

    const [message, status, departmentId] = useWatch({ control: form.control, name: ['message', 'status', 'department_id'] });
    const typed = useDebouncedValue(message, ESTIMATE_DELAY_MS);
    const estimate = useBroadcastEstimate(toBroadcastInput({ message: typed, status, department_id: departmentId }));
    // The figures are for the text as it was a moment ago; sending waits until they catch up with what is on screen.
    const upToDate = typed === message && estimate.data !== undefined && !estimate.isPlaceholderData;
    const canSend = upToDate && !estimate.isError && estimate.data.recipients > 0 && estimate.data.enough_credits;

    const review = form.handleSubmit((values) => setConfirming(toBroadcastInput(values)));

    const sendNow = (input: BroadcastInput) => {
        send.mutate(
            { input, idempotencyKey },
            {
                onSuccess: (queued) => {
                    toast.success(`Your message is on its way to ${formatCount(queued.estimated_recipients)} members.`);
                    void navigate(ROUTES.MESSAGES);
                },
                onError: (error) => {
                    setConfirming(undefined);

                    if (showFieldErrors(error, form.setError, ['message']).length === 0) {
                        toast.error(error.message);
                    }
                },
            },
        );
    };

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <PageHeader title="New broadcast" description="Send a text message to your members" />
            <form onSubmit={review} noValidate className="flex flex-col gap-3">
                <div className="grid gap-2 sm:grid-cols-2">
                    <SelectField
                        id="broadcast-status"
                        label="Send to members who are"
                        options={MEMBER_STATUSES}
                        {...form.register('status')}
                    />
                    <SelectField
                        id="broadcast-department"
                        label="In the department"
                        options={(departments.data?.data ?? []).map((department) => ({ value: department.id, label: department.name }))}
                        emptyLabel="Any department"
                        {...form.register('department_id')}
                    />
                </div>
                <TextAreaField
                    id="broadcast-message"
                    label="Message"
                    rows={5}
                    required
                    error={errors.message?.message}
                    {...form.register('message')}
                />
                <BroadcastEstimateNotice estimate={estimate} />
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={!canSend || send.isPending}>
                        Review and send
                    </Button>
                    <Button asChild variant="outline">
                        <Link to={ROUTES.MESSAGES}>Cancel</Link>
                    </Button>
                </div>
            </form>

            <ConfirmDialog
                open={confirming !== undefined}
                onOpenChange={(open) => !open && setConfirming(undefined)}
                title="Send this message?"
                description={
                    estimate.data === undefined
                        ? ''
                        : `It will be sent to ${formatCount(estimate.data.recipients)} members and use about ${formatCount(estimate.data.credits_needed)} SMS credits. A text cannot be called back once it is sent.`
                }
                confirmLabel="Send message"
                pending={send.isPending}
                onConfirm={() => confirming !== undefined && sendNow(confirming)}
            />
        </div>
    );
}
