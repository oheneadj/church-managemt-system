import { z } from 'zod';
import { PACKAGE_CREDITS_MAX, PACKAGE_NAME_MAX, PACKAGE_PRICE_MAX, PACKAGE_SORT_ORDER_MAX } from '@/constants/platform';
import { moneyToPesewas } from '@/utils/money';
import type { SmsPackage, SmsPackageInput } from '@/types/platform';

const MAX_PRICE_PESEWAS = moneyToPesewas(PACKAGE_PRICE_MAX) ?? 0n;

/** The checks on the package form. The price is read as exact text, never as a floating-point number. */
export const smsPackageSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Enter the name of the package')
        .max(PACKAGE_NAME_MAX, `Keep the name to ${PACKAGE_NAME_MAX} characters`),
    credits: z
        .string()
        .trim()
        .refine(
            (text) => /^\d+$/.test(text) && Number(text) >= 1 && Number(text) <= PACKAGE_CREDITS_MAX,
            `Credits must be a whole number from 1 to ${PACKAGE_CREDITS_MAX.toLocaleString('en-GB')}`,
        ),
    price: z
        .string()
        .trim()
        .refine((text) => {
            const pesewas = moneyToPesewas(text);

            return pesewas !== null && pesewas > 0n && pesewas <= MAX_PRICE_PESEWAS;
        }, `The price must be above 0 and at most ${PACKAGE_PRICE_MAX}, with at most two decimals`),
    sort_order: z
        .string()
        .trim()
        .refine(
            (text) => /^\d+$/.test(text) && Number(text) <= PACKAGE_SORT_ORDER_MAX,
            `The order must be a whole number from 0 to ${PACKAGE_SORT_ORDER_MAX}`,
        ),
});

export type SmsPackageValues = z.infer<typeof smsPackageSchema>;

/** A new package starts at the end of the list. */
export function blankPackageValues(packages: readonly SmsPackage[]): SmsPackageValues {
    const last = Math.max(-1, ...packages.map((one) => one.sort_order));

    return { name: '', credits: '', price: '', sort_order: String(Math.min(last + 1, PACKAGE_SORT_ORDER_MAX)) };
}

/** The form, filled in from a saved package. */
export function valuesFromPackage(one: SmsPackage): SmsPackageValues {
    return { name: one.name, credits: String(one.credits), price: one.price, sort_order: String(one.sort_order) };
}

/** Turns the form into what the API takes. The price stays the text that was typed. */
export function toPackageInput(values: SmsPackageValues): SmsPackageInput {
    return { name: values.name, credits: Number(values.credits), price: values.price, sort_order: Number(values.sort_order) };
}
