import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { TextAreaField } from '@/components/form/TextAreaField';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useFamilyMutations } from '@/hooks/useFamilies';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { Family } from '@/types/members';

const schema = z.object({
    name: z.string().trim().min(1, 'Enter the family name').max(255, 'Keep it under 255 characters'),
    notes: z.string().trim().max(2000, 'Keep it under 2000 characters'),
});

type FormValues = z.infer<typeof schema>;

interface FamilyFormDialogProps {
    /** The family to rename. Leave out to create a new one. */
    family?: Family;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Called with the saved family, for example to open a newly created one. */
    onSaved?: (family: Family) => void;
}

/**
 * A small form to create a family or change its name and notes. Who heads the family is set on the members
 * themselves (their role in the family), so that there can never be two heads.
 */
export function FamilyFormDialog({ family, open, onOpenChange, onSaved }: FamilyFormDialogProps) {
    const editing = family !== undefined;
    const { create, update } = useFamilyMutations();
    const form = useForm<FormValues>({
        resolver: zodResolver(schema),
        values: { name: family?.name ?? '', notes: family?.notes ?? '' },
    });
    const saving = create.isPending || update.isPending;

    const submit = form.handleSubmit((values) => {
        const input = { name: values.name, notes: values.notes === '' ? null : values.notes };
        const callbacks = {
            onSuccess: (saved: Family) => {
                toast.success(editing ? 'Family updated.' : 'Family created.');
                onOpenChange(false);
                onSaved?.(saved);
            },
            onError: (error: Error) => {
                if (showFieldErrors(error, form.setError, ['name', 'notes']).length === 0) {
                    toast.error(error.message);
                }
            },
        };

        if (editing) {
            update.mutate({ id: family.id, input }, callbacks);
        } else {
            create.mutate(input, callbacks);
        }
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{editing ? 'Edit family' : 'New family'}</DialogTitle>
                    <DialogDescription>
                        {editing ? 'Change the name or the notes.' : 'Then put members in it from their own pages.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} noValidate className="flex flex-col gap-3">
                    <TextField
                        id="family-name"
                        label="Family name"
                        required
                        placeholder="Mensah-Boateng Family"
                        error={form.formState.errors.name?.message}
                        {...form.register('name')}
                    />
                    <TextAreaField
                        id="family-notes"
                        label="Notes (optional)"
                        placeholder="For example: lives in two towns"
                        error={form.formState.errors.notes?.message}
                        {...form.register('notes')}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? 'Saving…' : editing ? 'Save' : 'Create family'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
