import { z } from 'zod';
import { ABSENCE_THRESHOLD_MAX, ABSENCE_THRESHOLD_MIN, YOUTH_AGE_LIMIT_MAX, YOUTH_AGE_LIMIT_MIN } from '@/constants/settings';
import type { ChurchSettings } from '@/types/church';

/** A whole number inside a range, typed into a box (so it arrives as text). */
function wholeNumberBetween(min: number, max: number, what: string) {
    return z
        .string()
        .trim()
        .refine(
            (text) => /^\d+$/.test(text) && Number(text) >= min && Number(text) <= max,
            `${what} must be a whole number from ${min} to ${max}`,
        );
}

/** The checks on the settings form. */
export const churchSettingsSchema = z.object({
    youth_age_limit: wholeNumberBetween(YOUTH_AGE_LIMIT_MIN, YOUTH_AGE_LIMIT_MAX, 'The youth age'),
    absence_threshold: wholeNumberBetween(ABSENCE_THRESHOLD_MIN, ABSENCE_THRESHOLD_MAX, 'The number of services'),
    receipt_on_cash: z.boolean(),
    absent_alert: z.boolean(),
});

export type ChurchSettingsValues = z.infer<typeof churchSettingsSchema>;

/** The form, filled in from the saved settings. */
export function valuesFromSettings(settings: ChurchSettings): ChurchSettingsValues {
    return {
        youth_age_limit: String(settings.youth_age_limit),
        absence_threshold: String(settings.absence_threshold),
        receipt_on_cash: settings.receipt_on_cash,
        absent_alert: settings.absent_alert,
    };
}

/** Turns the form into what the API takes. The schema has already checked the numbers. */
export function toSettingsInput(values: ChurchSettingsValues): ChurchSettings {
    return {
        youth_age_limit: Number(values.youth_age_limit),
        absence_threshold: Number(values.absence_threshold),
        receipt_on_cash: values.receipt_on_cash,
        absent_alert: values.absent_alert,
    };
}
