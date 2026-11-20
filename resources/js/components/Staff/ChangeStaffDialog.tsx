import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { SelectField } from '@/components/form/SelectField';
import { TextField } from '@/components/form/TextField';
import { changeStaffSchema, toStaffChange, valuesFromStaff, type ChangeStaffValues } from '@/components/Staff/staffFormSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { STAFF_ROLES } from '@/constants/enums';
import { useStaffMutations } from '@/hooks/useStaff';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { StaffMember } from '@/types/staff';

interface ChangeStaffDialogProps {
    staff: StaffMember;
    onClose: () => void;
}

const FIELDS = ['name', 'phone', 'role'] as const;

/**
 * Changes a staff member's name, phone number and role. The email is their sign-in and is shown but not editable. A new
 * role applies on their next request. Mount it only while there is someone to change, so each opening starts from them.
 */
export function ChangeStaffDialog({ staff, onClose }: ChangeStaffDialogProps) {
    const { update } = useStaffMutations();
    const form = useForm<ChangeStaffValues>({ resolver: zodResolver(changeStaffSchema), defaultValues: valuesFromStaff(staff) });
    const { errors } = form.formState;

    const save = form.handleSubmit((values) => {
        const input = toStaffChange(values);

        if (input === null) {
            return;
        }

        update.mutate(
            { id: staff.id, input },
            {
                onSuccess: () => {
                    toast.success('Changes saved.');
                    onClose();
                },
                onError: (error) => {
                    // A refusal such as "the last church admin" has no field of its own, so it is shown as a message.
                    if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
                        toast.error(error.message);
                    }
                },
            },
        );
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change {staff.name}</DialogTitle>
                    <DialogDescription>
                        Their email, <span className="font-medium">{staff.email}</span>, is how they sign in and cannot be changed here.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={save} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="change-name"
                        label="Name"
                        required
                        autoComplete="off"
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <TextField
                        id="change-phone"
                        label="Phone number"
                        type="tel"
                        inputMode="tel"
                        required
                        autoComplete="off"
                        error={errors.phone?.message}
                        {...form.register('phone')}
                    />
                    <SelectField
                        id="change-role"
                        label="Role"
                        required
                        options={STAFF_ROLES}
                        error={errors.role?.message}
                        {...form.register('role')}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={update.isPending}>
                            {update.isPending ? 'Saving…' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
