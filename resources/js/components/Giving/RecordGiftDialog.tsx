import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { toast } from 'sonner';
import { DateField } from '@/components/form/DateField';
import { SelectField } from '@/components/form/SelectField';
import { TextAreaField } from '@/components/form/TextAreaField';
import { AmountField } from '@/components/form/AmountField';
import { blankGiftValues, giftFormSchema, toGiftInput, type GiftFormValues } from '@/components/Giving/giftFormSchema';
import { MemberPicker } from '@/components/Members/MemberPicker';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useContributionMutations } from '@/hooks/useContributions';
import { useFundTypes } from '@/hooks/useFundTypes';
import { usePledgesForGift } from '@/hooks/usePledges';
import { useServicesOnDate } from '@/hooks/useServices';
import { newIdempotencyKey } from '@/lib/idempotencyKey';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { MemberSummary } from '@/types/members';
import { today } from '@/utils/today';

interface RecordGiftDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

const FIELDS = ['fund_type_id', 'amount', 'contributed_on', 'service_id', 'pledge_id', 'notes'] as const;

/**
 * Records a cash gift: who gave (or nobody, for an anonymous gift), which fund, how much, the day and, if there was
 * one, the service. The member gets a receipt text after the gift is saved (docs/04). Mobile money and card gifts start
 * a payment instead. The form is a separate component inside the dialog: the dialog's content is removed when it
 * closes, so every opening starts blank, with a new idempotency key, and nothing needs resetting by hand.
 */
export function RecordGiftDialog({ open, onOpenChange }: RecordGiftDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record a cash gift</DialogTitle>
                    <DialogDescription>
                        Search for the member who gave. Leave the member empty to record an anonymous gift.
                    </DialogDescription>
                </DialogHeader>
                <RecordGiftForm onClose={() => onOpenChange(false)} />
            </DialogContent>
        </Dialog>
    );
}

/** The member search, which sits above the form because a search box is itself a form and forms cannot nest, and the gift form. */
function RecordGiftForm({ onClose }: { onClose: () => void }) {
    const { record } = useContributionMutations();
    const funds = useFundTypes();
    const [member, setMember] = useState<MemberSummary | undefined>(undefined);
    // One key for one attempt: a double click or a retry after a failure records one gift.
    const [idempotencyKey] = useState(newIdempotencyKey);
    const form = useForm<GiftFormValues>({ resolver: zodResolver(giftFormSchema), defaultValues: blankGiftValues(today()) });
    const { errors } = form.formState;
    const day = useWatch({ control: form.control, name: 'contributed_on' });
    const services = useServicesOnDate(day);
    const fundId = useWatch({ control: form.control, name: 'fund_type_id' });
    const pledges = usePledgesForGift(member?.id ?? '', fundId);

    const fundOptions = (funds.data ?? []).map((fund) => ({ value: fund.id, label: fund.name }));
    const serviceOptions = (services.data?.data ?? []).map((service) => ({ value: service.id, label: service.name }));

    // Only a pledge that can still take a gift is offered: the server refuses one that is paid in full (D-047).
    const pledgeOptions = (pledges.data?.data ?? [])
        .filter((pledge) => pledge.status !== 'fulfilled')
        .map((pledge) => ({ value: pledge.id, label: `${pledge.description} · ${pledge.amount_outstanding_formatted} outstanding` }));

    const submit = form.handleSubmit((values) => {
        // A service chosen for another day, before the date was changed, is dropped rather than sent.
        const serviceId = serviceOptions.some((option) => option.value === values.service_id) ? values.service_id : undefined;
        // The same for a pledge chosen before the member or the fund was changed.
        const pledgeId = pledgeOptions.some((option) => option.value === values.pledge_id) ? values.pledge_id : undefined;

        record.mutate(
            { input: toGiftInput(values, member?.id, serviceId, pledgeId), idempotencyKey },
            {
                onSuccess: () => {
                    toast.success('Gift recorded.');
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
        <>
            <MemberPicker id="record-gift-member" label="Member (optional)" selected={member} onSelect={setMember} />
            <form onSubmit={submit} noValidate className="flex flex-col gap-2">
                <SelectField
                    id="record-gift-fund"
                    label="Fund"
                    required
                    options={fundOptions}
                    emptyLabel={funds.isPending ? 'Loading funds…' : 'Select a fund'}
                    error={errors.fund_type_id?.message}
                    {...form.register('fund_type_id')}
                />
                <AmountField
                    id="record-gift-amount"
                    label="Amount (GHS)"
                    required
                    error={errors.amount?.message}
                    {...form.register('amount')}
                />
                <DateField
                    id="record-gift-date"
                    label="Date given"
                    required
                    max={today()}
                    error={errors.contributed_on?.message}
                    {...form.register('contributed_on')}
                />
                <SelectField
                    id="record-gift-service"
                    label="Service (optional)"
                    options={serviceOptions}
                    emptyLabel={services.isPending && day !== '' ? 'Loading services…' : 'No service'}
                    error={errors.service_id?.message}
                    {...form.register('service_id')}
                />
                {pledgeOptions.length > 0 && (
                    <SelectField
                        id="record-gift-pledge"
                        label="Pays a pledge (optional)"
                        options={pledgeOptions}
                        emptyLabel="No pledge"
                        error={errors.pledge_id?.message}
                        {...form.register('pledge_id')}
                    />
                )}
                <TextAreaField id="record-gift-notes" label="Notes (optional)" error={errors.notes?.message} {...form.register('notes')} />
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={record.isPending}>
                        {record.isPending ? 'Saving…' : 'Record gift'}
                    </Button>
                </DialogFooter>
            </form>
        </>
    );
}
