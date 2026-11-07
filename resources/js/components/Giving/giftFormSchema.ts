import { z } from 'zod';
import { amountField } from '@/components/Giving/amountField';
import { GIFT_CASH_MAX, GIFT_PAST_DAY_TIME } from '@/constants/giving';
import { dateBox } from '@/lib/dateBox';
import type { ContributionInput } from '@/types/giving';
import { today } from '@/utils/today';

/** The checks on the record-a-gift form. The member is chosen apart from the form (a search box cannot sit inside it). */
export const giftFormSchema = z.object({
    fund_type_id: z.string().min(1, 'Choose the fund'),
    amount: amountField(GIFT_CASH_MAX, 'gift'),
    contributed_on: dateBox({
        required: 'Enter the date the gift was given',
        notAfter: { date: today, message: 'A gift cannot be dated in the future' },
    }),
    service_id: z.string(),
    pledge_id: z.string(),
    notes: z.string().trim().max(2000, 'Keep it under 2000 characters'),
});

export type GiftFormValues = z.infer<typeof giftFormSchema>;

/** A new gift starts dated today, with nothing chosen. `day` is today as `YYYY-MM-DD`. */
export function blankGiftValues(day: string): GiftFormValues {
    return { fund_type_id: '', amount: '', contributed_on: day, service_id: '', pledge_id: '', notes: '' };
}

/**
 * Turns the form values into the request body. A gift with no member is anonymous. The service and the pledge are passed
 * in already checked against what the form offered, so a choice made for another day, member or fund is never sent. A gift dated today is sent without a
 * time, so the server uses the moment it was entered; an earlier day is sent at noon UTC (see `GIFT_PAST_DAY_TIME`).
 * Empty optional boxes are left out.
 */
export function toGiftInput(
    values: GiftFormValues,
    memberId: string | undefined,
    serviceId: string | undefined,
    pledgeId?: string,
): ContributionInput {
    const day = values.contributed_on;

    return {
        member_id: memberId,
        service_id: serviceId,
        pledge_id: pledgeId,
        fund_type_id: values.fund_type_id,
        amount: values.amount,
        payment_method: 'cash',
        contributed_at: day === today() ? undefined : `${day}${GIFT_PAST_DAY_TIME}`,
        notes: values.notes || undefined,
    };
}
