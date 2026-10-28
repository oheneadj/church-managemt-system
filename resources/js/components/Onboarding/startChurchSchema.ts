import { z } from 'zod';
import { OTP_LENGTH, PREFIX_MAX_LENGTH, PREFIX_MIN_LENGTH, SLUG_MAX_LENGTH, SLUG_MIN_LENGTH, SLUG_PATTERN } from '@/constants/onboarding';
import { PHONE_INPUT_PATTERN } from '@/constants/validation';
import type { StartChurchInput } from '@/types/onboarding';

/**
 * The checks on the "start a church" form. The web address is optional: left empty, the server builds one
 * from the church name.
 */
export const startChurchSchema = z.object({
    church_name: z.string().trim().min(1, 'Enter the name of your church').max(255),
    slug: z
        .string()
        .trim()
        .toLowerCase()
        .refine((slug) => slug === '' || (slug.length >= SLUG_MIN_LENGTH && slug.length <= SLUG_MAX_LENGTH), {
            error: `Use ${SLUG_MIN_LENGTH} to ${SLUG_MAX_LENGTH} characters`,
        })
        .refine((slug) => slug === '' || SLUG_PATTERN.test(slug), { error: 'Use lowercase letters, numbers and single hyphens only' }),
    denomination: z.string().trim().max(255),
    city: z.string().trim().max(100),
    member_number_prefix: z
        .string()
        .trim()
        .regex(
            new RegExp(`^[A-Za-z]{${PREFIX_MIN_LENGTH},${PREFIX_MAX_LENGTH}}$`),
            `Use ${PREFIX_MIN_LENGTH} to ${PREFIX_MAX_LENGTH} letters, for example GRC`,
        ),
    admin_name: z.string().trim().min(1, 'Enter your name').max(255),
    admin_phone: z.string().trim().regex(PHONE_INPUT_PATTERN, 'Enter a valid phone number'),
    otp: z
        .string()
        .trim()
        .regex(new RegExp(`^\\d{${OTP_LENGTH}}$`), `Enter the ${OTP_LENGTH}-digit code`),
});

export type StartChurchValues = z.infer<typeof startChurchSchema>;

export const blankStartChurchValues: StartChurchValues = {
    church_name: '',
    slug: '',
    denomination: '',
    city: '',
    member_number_prefix: '',
    admin_name: '',
    admin_phone: '',
    otp: '',
};

/**
 * Turns the form values into the request body: empty optional boxes are left out.
 */
export function toStartChurchInput(values: StartChurchValues): StartChurchInput {
    return {
        church_name: values.church_name,
        slug: values.slug || undefined,
        denomination: values.denomination || undefined,
        city: values.city || undefined,
        member_number_prefix: values.member_number_prefix,
        admin_name: values.admin_name,
        admin_phone: values.admin_phone,
        otp: values.otp,
    };
}
