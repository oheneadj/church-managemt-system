import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router';
import { toast } from 'sonner';
import { z } from 'zod';
import { TextAreaField } from '@/components/form/TextAreaField';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ROUTES } from '@/constants/routes';
import { useMemberMutations } from '@/hooks/useMemberMutations';
import { showFieldErrors } from '@/lib/showFieldErrors';

const schema = z.object({
    reason: z.string().trim().min(3, 'Give a short reason').max(1000, 'Keep the reason under 1000 characters'),
});

type FormValues = z.infer<typeof schema>;

interface EraseMemberDialogProps {
    memberId: string;
    fullName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Erases a member's personal data at their request. It says plainly what goes and what stays, and asks for a
 * reason that is kept in the change log. It cannot be undone, so the button is worded as the action.
 */
export function EraseMemberDialog({ memberId, fullName, open, onOpenChange }: EraseMemberDialogProps) {
    const navigate = useNavigate();
    const { erase } = useMemberMutations();
    const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { reason: '' } });

    const submit = form.handleSubmit((values) => {
        erase.mutate(
            { id: memberId, reason: values.reason },
            {
                onSuccess: () => {
                    toast.success('Personal data erased.');
                    onOpenChange(false);
                    void navigate(ROUTES.MEMBERS);
                },
                onError: (error) => {
                    if (showFieldErrors(error, form.setError, ['reason']).length === 0) {
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
                    <DialogTitle>Erase personal data of {fullName}?</DialogTitle>
                    <DialogDescription>
                        Their name, phone, address, ID and other details, and their photo, are removed for good. The record stays archived
                        so giving and attendance history still add up. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} noValidate className="flex flex-col gap-3">
                    <TextAreaField
                        id="erase-reason"
                        label="Reason"
                        required
                        placeholder="For example: member asked in writing on 20 October"
                        error={form.formState.errors.reason?.message}
                        {...form.register('reason')}
                    />
                    <p className="text-sm text-muted-foreground">
                        The reason is kept in the change log. Please do not write personal details in it.
                    </p>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" disabled={erase.isPending}>
                            {erase.isPending ? 'Erasing…' : 'Erase personal data'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
