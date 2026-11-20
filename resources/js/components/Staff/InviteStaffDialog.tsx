import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { SelectField } from '@/components/form/SelectField';
import { TextField } from '@/components/form/TextField';
import { blankInviteValues, inviteStaffSchema, toStaffInviteInput, type InviteStaffValues } from '@/components/Staff/staffFormSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { STAFF_ROLES } from '@/constants/enums';
import { useStaffMutations } from '@/hooks/useStaff';
import { newIdempotencyKey } from '@/lib/idempotencyKey';
import { showFieldErrors } from '@/lib/showFieldErrors';

interface InviteStaffDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

const FIELDS = ['name', 'email', 'phone', 'role'] as const;

/**
 * Invites a staff member: their details and a role. They are emailed a code with which they choose their first password.
 * The form is its own component inside the dialog, so every opening starts empty with a new idempotency key.
 */
export function InviteStaffDialog({ open, onOpenChange }: InviteStaffDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Invite staff</DialogTitle>
                    <DialogDescription>
                        We email them a code to choose their password. If it runs out, they can use “Forgot your password?” on the sign-in
                        page.
                    </DialogDescription>
                </DialogHeader>
                <InviteStaffForm onClose={() => onOpenChange(false)} />
            </DialogContent>
        </Dialog>
    );
}

/** The invite form. */
function InviteStaffForm({ onClose }: { onClose: () => void }) {
    const { invite } = useStaffMutations();
    // One key for one attempt to invite: a double click or a retry after a failure sends one invitation.
    const [idempotencyKey] = useState(newIdempotencyKey);
    const form = useForm<InviteStaffValues>({ resolver: zodResolver(inviteStaffSchema), defaultValues: blankInviteValues() });
    const { errors } = form.formState;

    const send = form.handleSubmit((values) => {
        const input = toStaffInviteInput(values);

        if (input === null) {
            return;
        }

        invite.mutate(
            { input, idempotencyKey },
            {
                onSuccess: () => {
                    toast.success(`Invitation sent to ${input.email}.`);
                    onClose();
                },
                onError: (error) => {
                    if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
                        toast.error(error.message);
                    }
                },
            },
        );
    });

    return (
        <form onSubmit={send} noValidate className="flex flex-col gap-2">
            <TextField id="invite-name" label="Name" required autoComplete="off" error={errors.name?.message} {...form.register('name')} />
            <TextField
                id="invite-email"
                label="Email address"
                type="email"
                inputMode="email"
                required
                autoComplete="off"
                error={errors.email?.message}
                {...form.register('email')}
            />
            <TextField
                id="invite-phone"
                label="Phone number"
                type="tel"
                inputMode="tel"
                required
                autoComplete="off"
                placeholder="024 123 4567"
                error={errors.phone?.message}
                {...form.register('phone')}
            />
            <SelectField
                id="invite-role"
                label="Role"
                required
                options={STAFF_ROLES}
                emptyLabel="Choose a role"
                error={errors.role?.message}
                {...form.register('role')}
            />
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    Cancel
                </Button>
                <Button type="submit" disabled={invite.isPending}>
                    {invite.isPending ? 'Sending…' : 'Send invitation'}
                </Button>
            </DialogFooter>
        </form>
    );
}
