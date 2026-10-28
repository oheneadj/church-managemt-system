import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { SelectField } from '@/components/form/SelectField';
import { TextAreaField } from '@/components/form/TextAreaField';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { MILESTONE_TYPES, type MilestoneType } from '@/constants/enums';
import { useMemberMutations } from '@/hooks/useMemberMutations';
import { showFieldErrors } from '@/lib/showFieldErrors';
import { today } from '@/utils/today';

const schema = z.object({
    type: z.string().min(1, 'Choose a milestone'),
    occurred_at: z.string().min(1, 'Enter the date'),
    officiant: z.string().trim().max(255),
    cohort: z.string().trim().max(100),
    notes: z.string().trim().max(2000),
});

type FormValues = z.infer<typeof schema>;

interface AddMilestoneDialogProps {
    memberId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/** An empty optional box becomes null so the server stores nothing instead of an empty text. */
function orNull(text: string): string | null {
    return text === '' ? null : text;
}

/**
 * A small form to record a baptism, confirmation or similar milestone on a member.
 */
export function AddMilestoneDialog({ memberId, open, onOpenChange }: AddMilestoneDialogProps) {
    const { addMilestone } = useMemberMutations();
    const form = useForm<FormValues>({
        resolver: zodResolver(schema),
        defaultValues: { type: '', occurred_at: '', officiant: '', cohort: '', notes: '' },
    });
    const { errors } = form.formState;

    const submit = form.handleSubmit((values) => {
        addMilestone.mutate(
            {
                id: memberId,
                input: {
                    type: values.type as MilestoneType,
                    occurred_at: values.occurred_at,
                    officiant: orNull(values.officiant),
                    cohort: orNull(values.cohort),
                    notes: orNull(values.notes),
                },
            },
            {
                onSuccess: () => {
                    toast.success('Milestone added.');
                    form.reset();
                    onOpenChange(false);
                },
                onError: (error) => {
                    if (showFieldErrors(error, form.setError, ['type', 'occurred_at', 'officiant', 'cohort', 'notes']).length === 0) {
                        toast.error(error.message);
                    }
                },
            },
        );
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add milestone</DialogTitle>
                    <DialogDescription>For example a baptism, a confirmation or a dedication.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} noValidate className="flex flex-col gap-3">
                    <SelectField
                        id="milestone-type"
                        label="Milestone"
                        required
                        options={MILESTONE_TYPES}
                        emptyLabel="Choose a milestone"
                        error={errors.type?.message}
                        {...form.register('type')}
                    />
                    <TextField
                        id="milestone-date"
                        label="Date"
                        type="date"
                        required
                        max={today()}
                        error={errors.occurred_at?.message}
                        {...form.register('occurred_at')}
                    />
                    <TextField
                        id="milestone-officiant"
                        label="Officiant (optional)"
                        placeholder="Pastor Mensah"
                        error={errors.officiant?.message}
                        {...form.register('officiant')}
                    />
                    <TextField
                        id="milestone-cohort"
                        label="Class or cohort (optional)"
                        placeholder="Baptism class of 2026"
                        error={errors.cohort?.message}
                        {...form.register('cohort')}
                    />
                    <TextAreaField
                        id="milestone-notes"
                        label="Notes (optional)"
                        placeholder="Anything worth remembering about this milestone"
                        error={errors.notes?.message}
                        {...form.register('notes')}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={addMilestone.isPending}>
                            {addMilestone.isPending ? 'Saving…' : 'Add milestone'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
