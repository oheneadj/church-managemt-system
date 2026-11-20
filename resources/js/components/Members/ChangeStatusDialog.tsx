import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { SelectField } from '@/components/form/SelectField';
import { TextAreaField } from '@/components/form/TextAreaField';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { LEAVING_STATUSES, MEMBER_STATUSES, type MemberStatus } from '@/constants/enums';
import { PERMISSIONS } from '@/constants/permissions';
import { useCan } from '@/hooks/useCan';
import { useMemberMutations } from '@/hooks/useMemberMutations';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { Member } from '@/types/members';

const schema = z.object({
    status: z.string().min(1, 'Choose a status'),
    note: z.string().trim().max(1000, 'Keep the note under 1000 characters'),
});

type FormValues = z.infer<typeof schema>;

interface ChangeStatusDialogProps {
    member: Member;
    fullName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Moves a member to another status, with a note that is kept as the reason. Moving someone out of active
 * church life (inactive, transferred, deceased, archived) asks "Are you sure?" first and needs the deactivate permission.
 */
export function ChangeStatusDialog({ member, fullName, open, onOpenChange }: ChangeStatusDialogProps) {
    const canDeactivate = useCan(PERMISSIONS.MEMBERS_DEACTIVATE);
    const { changeStatus } = useMemberMutations();
    const [confirming, setConfirming] = useState<FormValues | null>(null);
    const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { status: '', note: '' } });

    const choices = MEMBER_STATUSES.filter(
        (status) => status.value !== member.status && (canDeactivate || !LEAVING_STATUSES.includes(status.value)),
    );

    function apply(values: FormValues) {
        changeStatus.mutate(
            { id: member.id, status: values.status as MemberStatus, note: values.note === '' ? null : values.note },
            {
                onSuccess: () => {
                    toast.success('Status updated.');
                    setConfirming(null);
                    form.reset();
                    onOpenChange(false);
                },
                onError: (error) => {
                    setConfirming(null);
                    if (showFieldErrors(error, form.setError, ['status', 'note']).length === 0) {
                        toast.error(error.message);
                    }
                },
            },
        );
    }

    const submit = form.handleSubmit((values) => {
        if (LEAVING_STATUSES.includes(values.status as MemberStatus)) {
            setConfirming(values);
        } else {
            apply(values);
        }
    });

    const confirmedLabel = MEMBER_STATUSES.find((status) => status.value === confirming?.status)?.label;

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Change status</DialogTitle>
                        <DialogDescription>
                            {fullName} is {member.status_label.toLowerCase()} now.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} noValidate className="flex flex-col gap-3">
                        <SelectField
                            id="status"
                            label="New status"
                            required
                            options={choices}
                            emptyLabel="Choose a status"
                            error={form.formState.errors.status?.message}
                            {...form.register('status')}
                        />
                        <TextAreaField
                            id="status-note"
                            label="Reason (optional)"
                            placeholder="For example: relocated to Kumasi"
                            error={form.formState.errors.note?.message}
                            {...form.register('note')}
                        />
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={changeStatus.isPending}>
                                {changeStatus.isPending ? 'Saving…' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(isOpen) => !isOpen && setConfirming(null)}
                title={`Move ${fullName} to ${confirmedLabel}?`}
                description="They will leave the default directory. Their record, giving and attendance history are kept, and you can change the status back later."
                confirmLabel={`Move to ${confirmedLabel}`}
                pending={changeStatus.isPending}
                onConfirm={() => confirming && apply(confirming)}
            />
        </>
    );
}
