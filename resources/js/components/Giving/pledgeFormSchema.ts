import { z } from 'zod';
import { amountField } from '@/components/Giving/amountField';
import { PLEDGE_DESCRIPTION_MAX, PLEDGE_MAX } from '@/constants/giving';
import { dateBox } from '@/lib/dateBox';
import type { Pledge, PledgeChange } from '@/types/giving';
import { today } from '@/utils/today';

/**
 * The checks on the pledge form. A pledge's target date must be today or later when the pledge is made, and when it is
 * changed to a new date; saving a pledge again with its own old date, even one that has passed, is fine (docs/08), so the
 * change form passes that date as `unchangedDate`. A new pledge needs a fund (`requireFund`); a change cannot alter the
 * fund. The member is chosen apart from the form.
 */
export function pledgeFormSchema({ unchangedDate, requireFund = false }: { unchangedDate?: string; requireFund?: boolean } = {}) {
    return z
        .object({
            fund_type_id: z.string(),
            description: z
                .string()
                .trim()
                .min(1, 'Say what the pledge is for')
                .max(PLEDGE_DESCRIPTION_MAX, `Keep it under ${PLEDGE_DESCRIPTION_MAX} characters`),
            amount_pledged: amountField(PLEDGE_MAX, 'pledge'),
            target_date: dateBox({ required: 'Enter the target date' }),
        })
        .superRefine((values, context) => {
            const iso = values.target_date;

            if (requireFund && values.fund_type_id === '') {
                context.addIssue({ code: 'custom', path: ['fund_type_id'], message: 'Choose the fund' });
            }

            // A box that is empty or not a real date already has its message from the date box.
            if (iso !== '' && iso !== unchangedDate && iso < today()) {
                context.addIssue({ code: 'custom', path: ['target_date'], message: 'The target date must be today or later' });
            }
        });
}

export type PledgeFormValues = z.infer<ReturnType<typeof pledgeFormSchema>>;

/** A new pledge starts with nothing filled in. */
export function blankPledgeValues(): PledgeFormValues {
    return { fund_type_id: '', description: '', amount_pledged: '', target_date: '' };
}

/** The change form, filled in from a saved pledge. */
export function valuesFromPledge(pledge: Pledge): PledgeFormValues {
    return {
        fund_type_id: pledge.fund_type.id,
        description: pledge.description,
        amount_pledged: pledge.amount_pledged,
        target_date: pledge.target_date,
    };
}

/** Turns the form values into what the API takes for a change. */
export function toPledgeChange(values: PledgeFormValues): PledgeChange {
    return {
        description: values.description,
        amount_pledged: values.amount_pledged,
        target_date: values.target_date,
    };
}
