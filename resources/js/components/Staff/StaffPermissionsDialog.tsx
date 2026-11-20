import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { permissionChange } from '@/components/Staff/permissionChange';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { STAFF_PERMISSIONS, STAFF_ROLES } from '@/constants/enums';
import { useStaffMutations } from '@/hooks/useStaff';
import type { StaffMember } from '@/types/staff';

interface StaffPermissionsDialogProps {
    staff: StaffMember;
    onClose: () => void;
}

interface PermissionValues {
    /** The extra permissions that are ticked. */
    extras: string[];
}

/** The words for the person's role, or the role as sent if the app does not know it. */
function roleName(staff: StaffMember): string {
    const role = staff.roles[0] ?? '';

    return STAFF_ROLES.find((option) => option.value === role)?.label ?? role;
}

/**
 * Gives a person permissions on top of their role, or takes those extras away. Everything their role already gives is ticked
 * and cannot be changed here (to take one of those away, change their role). Only a change is sent. A new set of permissions
 * applies on their next request. Mount it only while there is someone to change, so each opening starts from them.
 */
export function StaffPermissionsDialog({ staff, onClose }: StaffPermissionsDialogProps) {
    const { setPermissions } = useStaffMutations();
    const form = useForm<PermissionValues>({ defaultValues: { extras: staff.extra_permissions } });
    const ticked = useWatch({ control: form.control, name: 'extras' });
    const change = permissionChange(staff, ticked);

    const save = form.handleSubmit(() => {
        if (change === null) {
            return;
        }

        setPermissions.mutate(
            { id: staff.id, change },
            {
                onSuccess: () => {
                    toast.success('Permissions saved. They apply the next time they use the app.');
                    onClose();
                },
                onError: (error) => toast.error(error.message),
            },
        );
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90svh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Permissions for {staff.name}</DialogTitle>
                    <DialogDescription>
                        As {roleName(staff)} they can already do the ticked things that are greyed out. Tick more to allow them. To take
                        away something their role gives, change their role instead.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={save} noValidate className="flex flex-col gap-3">
                    <fieldset className="flex flex-col gap-2">
                        <legend className="sr-only">Permissions</legend>
                        {STAFF_PERMISSIONS.map((permission) => {
                            const fromRole = staff.role_permissions.includes(permission.value);
                            const id = `permission-${permission.value}`;

                            return (
                                <div key={permission.value} className="flex items-center gap-2">
                                    {fromRole ? (
                                        <input id={id} type="checkbox" className="size-5 accent-primary" checked disabled readOnly />
                                    ) : (
                                        <input
                                            id={id}
                                            type="checkbox"
                                            className="size-5 accent-primary"
                                            value={permission.value}
                                            {...form.register('extras')}
                                        />
                                    )}
                                    <label htmlFor={id} className="text-sm">
                                        {permission.label}
                                        {fromRole && <span className="text-muted-foreground"> (from their role)</span>}
                                    </label>
                                </div>
                            );
                        })}
                    </fieldset>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={change === null || setPermissions.isPending}>
                            {setPermissions.isPending ? 'Saving…' : 'Save permissions'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
