import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { SelectField } from '@/components/form/SelectField';
import { TextAreaField } from '@/components/form/TextAreaField';
import { TextField } from '@/components/form/TextField';
import {
    blankDepartmentValues,
    departmentSchema,
    toDepartmentInput,
    valuesFromDepartment,
    type DepartmentValues,
} from '@/components/Departments/departmentSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDepartmentMutations } from '@/hooks/useDepartments';
import { useStaffDirectory } from '@/hooks/useStaff';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { Department } from '@/types/members';

interface DepartmentDialogProps {
    /** The department to change, or none to add a new one. */
    department?: Department;
    onClose: () => void;
}

const FIELDS = ['name', 'description', 'head_user_id'] as const;

/**
 * Adds a department or changes one: its name and what it does. Mount it only while it is open, so each opening starts from
 * the department (or blank). A name another department already has is refused by the server and shown beside the box. The head
 * is chosen from the church's active staff. Until that list has loaded the choice is off, and a department's head is only sent
 * when it is changed, so saving the name alone never changes the head.
 */
export function DepartmentDialog({ department, onClose }: DepartmentDialogProps) {
    const { create, update } = useDepartmentMutations();
    const staff = useStaffDirectory();
    const form = useForm<DepartmentValues>({
        resolver: zodResolver(departmentSchema),
        defaultValues: department === undefined ? blankDepartmentValues() : valuesFromDepartment(department),
    });
    const { errors } = form.formState;
    const pending = create.isPending || update.isPending;
    // The current head is always among the choices, even once they are no longer active and so not in the directory.
    const names = staff.data ?? [];
    const headOptions = [
        ...names.map((person) => ({ value: person.id, label: person.name })),
        ...(department?.head && !names.some((person) => person.id === department.head?.id)
            ? [{ value: department.head.id, label: `${department.head.name} (not active)` }]
            : []),
    ];

    const save = form.handleSubmit((values) => {
        const input = toDepartmentInput(values, staff.isSuccess, department);
        const done = {
            onSuccess: () => {
                toast.success(department === undefined ? `${input.name} is added.` : 'Changes saved.');
                onClose();
            },
            onError: (error: Error) => {
                if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
                    toast.error(error.message);
                }
            },
        };

        if (department === undefined) {
            create.mutate(input, done);
        } else {
            update.mutate({ id: department.id, input }, done);
        }
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{department === undefined ? 'Add a department' : `Change ${department.name}`}</DialogTitle>
                    <DialogDescription>
                        A department is a group in the church, such as the choir or the ushers, that members can belong to.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={save} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="department-name"
                        label="Name"
                        required
                        autoComplete="off"
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <SelectField
                        id="department-head"
                        label="Head of the department"
                        options={headOptions}
                        emptyLabel="Nobody yet"
                        disabled={!staff.isSuccess}
                        error={errors.head_user_id?.message}
                        {...form.register('head_user_id')}
                    />
                    {staff.isError && (
                        <p className="text-sm text-muted-foreground">
                            The list of staff could not be loaded, so the head cannot be changed now.
                        </p>
                    )}
                    <TextAreaField
                        id="department-description"
                        label="What it does"
                        error={errors.description?.message}
                        {...form.register('description')}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={pending}>
                            {pending ? 'Saving…' : department === undefined ? 'Add department' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
