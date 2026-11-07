import { zodResolver } from '@hookform/resolvers/zod';
import { useMemo, useState, type SyntheticEvent } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { DateField } from '@/components/form/DateField';
import { SelectField } from '@/components/form/SelectField';
import { TextField } from '@/components/form/TextField';
import {
    blankPledgeValues,
    pledgeFormSchema,
    toPledgeChange,
    valuesFromPledge,
    type PledgeFormValues,
} from '@/components/Giving/pledgeFormSchema';
import { MemberPicker } from '@/components/Members/MemberPicker';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useFundTypes } from '@/hooks/useFundTypes';
import { usePledgeMutations } from '@/hooks/usePledges';
import { newIdempotencyKey } from '@/lib/idempotencyKey';
import { showFieldErrors } from '@/lib/showFieldErrors';
import { today } from '@/utils/today';
import type { Pledge } from '@/types/giving';
import type { MemberSummary } from '@/types/members';

interface PledgeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The pledge to change. Leave out to make a new one. */
    pledge?: Pledge;
}

const FIELDS = ['fund_type_id', 'description', 'amount_pledged', 'target_date'] as const;

/**
 * Makes a pledge, or changes one. A new pledge needs the member and the fund; a change can only alter the description,
 * the amount and the target date, because gifts are linked to the member and fund. The form is its own component inside
 * the dialog, so every opening starts fresh (with a new idempotency key) and nothing is reset by hand.
 */
export function PledgeDialog({ open, onOpenChange, pledge }: PledgeDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{pledge === undefined ? 'New pledge' : 'Change pledge'}</DialogTitle>
                    <DialogDescription>
                        {pledge === undefined
                            ? 'A promise to give an amount to a fund by a date. Gifts that pay it are linked to it when they are recorded.'
                            : 'The member and fund cannot change, because gifts are linked to them. To stop a pledge, lower its amount to what has been paid.'}
                    </DialogDescription>
                </DialogHeader>
                <PledgeForm pledge={pledge} onClose={() => onOpenChange(false)} />
            </DialogContent>
        </Dialog>
    );
}

/** The member search (above the form, because a search box is itself a form) and the pledge form. */
function PledgeForm({ pledge, onClose }: { pledge: Pledge | undefined; onClose: () => void }) {
    const { create, update } = usePledgeMutations();
    const funds = useFundTypes();
    const [member, setMember] = useState<MemberSummary | undefined>(undefined);
    const [memberMissing, setMemberMissing] = useState(false);
    // One key for one attempt to make a pledge: a double click or a retry after a failure makes one.
    const [idempotencyKey] = useState(newIdempotencyKey);
    const schema = useMemo(() => pledgeFormSchema({ unchangedDate: pledge?.target_date, requireFund: pledge === undefined }), [pledge]);
    const form = useForm<PledgeFormValues>({
        resolver: zodResolver(schema),
        defaultValues: pledge === undefined ? blankPledgeValues() : valuesFromPledge(pledge),
    });
    const { errors } = form.formState;
    const pending = create.isPending || update.isPending;
    const fundOptions = (funds.data ?? []).map((fund) => ({ value: fund.id, label: fund.name }));

    const failed = (error: Error) => {
        if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
            toast.error(error.message);
        }
    };

    const save = form.handleSubmit((values) => {
        if (pledge !== undefined) {
            update.mutate(
                { id: pledge.id, input: toPledgeChange(values) },
                {
                    onSuccess: () => {
                        toast.success('Pledge changed.');
                        onClose();
                    },
                    onError: failed,
                },
            );

            return;
        }

        if (member === undefined) {
            return;
        }

        create.mutate(
            { input: { member_id: member.id, fund_type_id: values.fund_type_id, ...toPledgeChange(values) }, idempotencyKey },
            {
                onSuccess: () => {
                    toast.success('Pledge made.');
                    onClose();
                },
                onError: failed,
            },
        );
    });

    // The member is checked here, beside the form's own checks, so every missing thing is shown at once.
    const submit = (event: SyntheticEvent<HTMLFormElement>) => {
        setMemberMissing(pledge === undefined && member === undefined);

        return save(event);
    };

    return (
        <>
            {pledge === undefined ? (
                <div className="flex flex-col gap-1">
                    <MemberPicker
                        id="pledge-member"
                        label="Member"
                        selected={member}
                        onSelect={(chosen) => {
                            setMember(chosen);
                            setMemberMissing(false);
                        }}
                    />
                    {memberMissing && (
                        <p role="alert" className="text-sm text-destructive">
                            Choose the member who made the pledge
                        </p>
                    )}
                </div>
            ) : (
                <p className="text-sm">
                    <span className="font-medium">{pledge.member.full_name}</span> · {pledge.fund_type.name}
                </p>
            )}
            <form onSubmit={submit} noValidate className="flex flex-col gap-2">
                {pledge === undefined && (
                    <SelectField
                        id="pledge-fund"
                        label="Fund"
                        required
                        options={fundOptions}
                        emptyLabel={funds.isPending ? 'Loading funds…' : 'Select a fund'}
                        error={errors.fund_type_id?.message}
                        {...form.register('fund_type_id')}
                    />
                )}
                <TextField
                    id="pledge-description"
                    label="What it is for"
                    required
                    placeholder="Building Fund 2026 pledge"
                    error={errors.description?.message}
                    {...form.register('description')}
                />
                <TextField
                    id="pledge-amount"
                    label="Amount pledged (GHS)"
                    required
                    type="number"
                    inputMode="decimal"
                    step="0.01"
                    min="0.01"
                    placeholder="0.00"
                    error={errors.amount_pledged?.message}
                    {...form.register('amount_pledged')}
                />
                <DateField
                    id="pledge-target-date"
                    label="Target date"
                    required
                    min={pledge === undefined ? today() : undefined}
                    error={errors.target_date?.message}
                    {...form.register('target_date')}
                />
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={pending}>
                        {pending ? 'Saving…' : pledge === undefined ? 'Make pledge' : 'Save changes'}
                    </Button>
                </DialogFooter>
            </form>
        </>
    );
}
