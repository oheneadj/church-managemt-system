import { z } from 'zod';
import { GIFT_CASH_MAX, GIFT_PAST_DAY_TIME } from '@/constants/giving';
import { dateBox } from '@/lib/dateBox';
import type { ContributionInput } from '@/types/giving';
import { dateTextToIso } from '@/utils/dateText';
import { moneyToPesewas } from '@/utils/money';
import { today } from '@/utils/today';

const MOST_PESEWAS = moneyToPesewas(GIFT_CASH_MAX) ?? 0n;

/** The checks on the record-a-gift form. The member is chosen apart from the form (a search box cannot sit inside it). */
export const giftFormSchema = z.object({
    fund_type_id: z.string().min(1, 'Choose the fund'),
    amount: z
        .string()
        .trim()
        .superRefine((text, context) => {
            const pesewas = moneyToPesewas(text);

            if (text === '') {
                context.addIssue({ code: 'custom', message: 'Enter the amount' });
            } else if (pesewas === null) {
                context.addIssue({ code: 'custom', message: 'Enter an amount in cedis, with at most two decimals' });
            } else if (pesewas === 0n) {
                context.addIssue({ code: 'custom', message: 'The amount must be more than 0' });
            } else if (pesewas > MOST_PESEWAS) {
                context.addIssue({ code: 'custom', message: `One gift can be at most ${GIFT_CASH_MAX}` });
            }
        }),
    contributed_on: dateBox({
        required: 'Enter the date the gift was given',
        notAfter: { date: today, message: 'A gift cannot be dated in the future' },
    }),
    service_id: z.string(),
    notes: z.string().trim().max(2000, 'Keep it under 2000 characters'),
});

export type GiftFormValues = z.infer<typeof giftFormSchema>;

/** A new gift starts dated today, with nothing chosen. `todayText` is today as the date box shows it. */
export function blankGiftValues(todayText: string): GiftFormValues {
    return { fund_type_id: '', amount: '', contributed_on: todayText, service_id: '', notes: '' };
}

/**
 * Turns the form values into the request body. A gift with no member is anonymous. A gift dated today is sent without a
 * time, so the server uses the moment it was entered; an earlier day is sent at noon UTC (see `GIFT_PAST_DAY_TIME`).
 * Empty optional boxes are left out.
 */
export function toGiftInput(values: GiftFormValues, memberId: string | undefined, serviceId: string | undefined): ContributionInput {
    const day = dateTextToIso(values.contributed_on) ?? values.contributed_on;

    return {
        member_id: memberId,
        service_id: serviceId,
        fund_type_id: values.fund_type_id,
        amount: values.amount,
        payment_method: 'cash',
        contributed_at: day === today() ? undefined : `${day}${GIFT_PAST_DAY_TIME}`,
        notes: values.notes || undefined,
    };
}
