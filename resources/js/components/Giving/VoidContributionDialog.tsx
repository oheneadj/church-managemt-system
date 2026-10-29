import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { TextAreaField } from '@/components/form/TextAreaField';
import { voidReasonSchema, type VoidReasonValues } from '@/components/Giving/voidReasonSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useContributionMutations } from '@/hooks/useContributions';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { Contribution } from '@/types/giving';

interface VoidContributionDialogProps {
    /** The gift to void. The dialog is open while there is one. */
    gift: Contribution | undefined;
    onClose: () => void;
}

/**
 * Asks why a gift is being voided and voids it. A void cannot be undone: the gift stays on record, marked, and drops out
 * of every total. To correct an amount, void the gift and record a new one.
 */
export function VoidContributionDialog({ gift, onClose }: VoidContributionDialogProps) {
    const { voidGift } = useContributionMutations();
    const form = useForm<VoidReasonValues>({ resolver: zodResolver(voidReasonSchema), defaultValues: { reason: '' } });
    const { errors } = form.formState;

    useEffect(() => {
        if (gift !== undefined) form.reset({ reason: '' });
    }, [gift, form]);

    const submit = form.handleSubmit((values) => {
        if (gift === undefined) return;

        voidGift.mutate(
            { id: gift.id, reason: values.reason },
            {
                onSuccess: () => {
                    toast.success('Gift voided.');
                    onClose();
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
        <Dialog open={gift !== undefined} onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Void this gift?</DialogTitle>
                    <DialogDescription>
                        {gift ? `${gift.amount_formatted} to ${gift.fund_type.name}` : ''}. It stays on record, marked as voided, and is
                        left out of every total. To correct an amount, void it and record the right one.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} noValidate className="flex flex-col gap-2">
                    <TextAreaField
                        id="void-reason"
                        label="Reason"
                        required
                        placeholder="Entered twice by mistake"
                        error={errors.reason?.message}
                        {...form.register('reason')}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Keep the gift
                        </Button>
                        <Button type="submit" variant="destructive" disabled={voidGift.isPending}>
                            {voidGift.isPending ? 'Voiding…' : 'Void gift'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
