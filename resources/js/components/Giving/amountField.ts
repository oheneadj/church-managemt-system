import { z } from 'zod';
import { moneyToPesewas } from '@/utils/money';

/**
 * The check for an amount of cedis typed into a form: it must be there, be an amount with at most two decimals, be more
 * than nothing and not be more than `max`. It is checked exactly, in whole pesewas, never as a floating-point number
 * (D-017). `thing` is what the amount is for ("gift", "pledge") and appears in the message about the largest amount.
 */
export function amountField(max: string, thing: string) {
    const mostPesewas = moneyToPesewas(max) ?? 0n;

    return z
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
            } else if (pesewas > mostPesewas) {
                context.addIssue({ code: 'custom', message: `One ${thing} can be at most ${max}` });
            }
        });
}
