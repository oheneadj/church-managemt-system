import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
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
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { Department } from '@/types/members';

interface DepartmentDialogProps {
    /** The department to change, or none to add a new one. */
    department?: Department;
    onClose: () => void;
}

const FIELDS = ['name', 'description'] as const;

/**
 * Adds a department or changes one: its name and what it does. Mount it only while it is open, so each opening starts from
 * the department (or blank). A name another department already has is refused by the server and shown beside the box. The
 * department's head is not part of this form, so changing a department never changes its head.
 */
export function DepartmentDialog({ department, onClose }: DepartmentDialogProps) {
    const { create, update } = useDepartmentMutations();
    const form = useForm<DepartmentValues>({
        resolver: zodResolver(departmentSchema),
        defaultValues: department === undefined ? blankDepartmentValues() : valuesFromDepartment(department),
    });
    const { errors } = form.formState;
    const pending = create.isPending || update.isPending;

    const save = form.handleSubmit((values) => {
        const input = toDepartmentInput(values);
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
