import { z } from 'zod';
import { FUND_DESCRIPTION_MAX, FUND_NAME_MAX, FUND_SORT_ORDER_MAX } from '@/constants/giving';
import type { FundType, FundTypeInput } from '@/types/giving';

/** The checks on the fund form. The server also refuses a name another fund already has, whatever its capitals. */
export const fundTypeSchema = z.object({
    name: z.string().trim().min(1, 'Enter the name of the fund').max(FUND_NAME_MAX, `Keep the name to ${FUND_NAME_MAX} characters`),
    description: z.string().trim().max(FUND_DESCRIPTION_MAX, `Keep the description to ${FUND_DESCRIPTION_MAX} characters`),
    sort_order: z
        .string()
        .trim()
        .refine(
            (text) => /^\d+$/.test(text) && Number(text) <= FUND_SORT_ORDER_MAX,
            `The order must be a whole number from 0 to ${FUND_SORT_ORDER_MAX}`,
        ),
});

export type FundTypeValues = z.infer<typeof fundTypeSchema>;

/**
 * A new fund starts at the end of the list: one place after the last fund, held at the highest order the server accepts.
 * The people who run a church rarely care about the number, only that the new fund is not in the middle of the others.
 */
export function blankFundValues(funds: readonly FundType[]): FundTypeValues {
    const last = Math.max(-1, ...funds.map((fund) => fund.sort_order));

    return { name: '', description: '', sort_order: String(Math.min(last + 1, FUND_SORT_ORDER_MAX)) };
}

/** The form, filled in from a saved fund. */
export function valuesFromFund(fund: FundType): FundTypeValues {
    return { name: fund.name, description: fund.description ?? '', sort_order: String(fund.sort_order) };
}

/** Turns the form into what the API takes. An empty description clears it. */
export function toFundTypeInput(values: FundTypeValues): FundTypeInput {
    return { name: values.name, description: values.description === '' ? null : values.description, sort_order: Number(values.sort_order) };
}
