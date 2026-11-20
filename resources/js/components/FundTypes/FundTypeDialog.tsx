import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { TextAreaField } from '@/components/form/TextAreaField';
import { TextField } from '@/components/form/TextField';
import {
    blankFundValues,
    fundTypeSchema,
    toFundTypeInput,
    valuesFromFund,
    type FundTypeValues,
} from '@/components/FundTypes/fundTypeSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FUND_SORT_ORDER_MAX } from '@/constants/giving';
import { useFundTypeMutations } from '@/hooks/useFundTypes';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { FundType } from '@/types/giving';

interface FundTypeDialogProps {
    /** The fund to change, or none to add a new one. */
    fund?: FundType;
    /** Every fund now, so a new one can be placed after the last. */
    funds: readonly FundType[];
    onClose: () => void;
}

const FIELDS = ['name', 'description', 'sort_order'] as const;

/**
 * Adds a fund or changes one: its name, what it is for, and where it sits in the list. Mount it only while it is open, so
 * each opening starts from the fund (or blank). A name another fund already has, whatever its capitals, is refused by the server
 * and shown beside the box. There is no delete: a fund that is no longer used is switched off from the list.
 */
export function FundTypeDialog({ fund, funds, onClose }: FundTypeDialogProps) {
    const { create, update } = useFundTypeMutations();
    const form = useForm<FundTypeValues>({
        resolver: zodResolver(fundTypeSchema),
        defaultValues: fund === undefined ? blankFundValues(funds) : valuesFromFund(fund),
    });
    const { errors } = form.formState;
    const pending = create.isPending || update.isPending;

    const save = form.handleSubmit((values) => {
        const input = toFundTypeInput(values);
        const done = {
            onSuccess: () => {
                toast.success(fund === undefined ? `${input.name} is added.` : 'Changes saved.');
                onClose();
            },
            onError: (error: Error) => {
                if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
                    toast.error(error.message);
                }
            },
        };

        if (fund === undefined) {
            create.mutate(input, done);
        } else {
            update.mutate({ id: fund.id, input }, done);
        }
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{fund === undefined ? 'Add a fund' : `Change ${fund.name}`}</DialogTitle>
                    <DialogDescription>
                        A fund is what a gift is given for, such as Tithe or the Building Fund. It cannot be deleted, only switched off.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={save} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="fund-name"
                        label="Name"
                        required
                        autoComplete="off"
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <TextAreaField
                        id="fund-description"
                        label="What it is for"
                        error={errors.description?.message}
                        {...form.register('description')}
                    />
                    <TextField
                        id="fund-order"
                        label="Place in the list"
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={FUND_SORT_ORDER_MAX}
                        error={errors.sort_order?.message}
                        {...form.register('sort_order')}
                    />
                    <p className="text-sm text-muted-foreground">Funds are listed from the lowest number to the highest.</p>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={pending}>
                            {pending ? 'Saving…' : fund === undefined ? 'Add fund' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
