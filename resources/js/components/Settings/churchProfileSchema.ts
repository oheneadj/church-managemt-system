import { z } from 'zod';
import { PHONE_INPUT_PATTERN } from '@/constants/validation';
import type { ChurchProfile, ChurchProfileInput } from '@/types/church';

/** The longest each detail may be, as the server allows (docs/08, Update Church Profile). */
const NAME_MAX = 255;
const ADDRESS_MAX = 1000;
const TOWN_MAX = 100;

/** An optional detail: it may be left empty. */
const optionalText = (max: number) => z.string().trim().max(max);

/** The checks on the church details form. Only the name is needed; the phone and email are checked only when given. */
export const churchProfileSchema = z.object({
    name: z.string().trim().min(1, 'Enter the name of the church').max(NAME_MAX),
    denomination: optionalText(NAME_MAX),
    address: optionalText(ADDRESS_MAX),
    city: optionalText(TOWN_MAX),
    region: optionalText(TOWN_MAX),
    phone: z
        .string()
        .trim()
        .refine((phone) => phone === '' || PHONE_INPUT_PATTERN.test(phone), 'Enter a valid phone number'),
    email: z
        .string()
        .trim()
        .toLowerCase()
        .max(NAME_MAX)
        .refine((email) => email === '' || z.email().safeParse(email).success, 'Enter a valid email address'),
});

export type ChurchProfileValues = z.infer<typeof churchProfileSchema>;

/** The form, filled in from the saved details. A detail the church never gave is an empty box. */
export function valuesFromProfile(profile: ChurchProfile): ChurchProfileValues {
    return {
        name: profile.name,
        denomination: profile.denomination ?? '',
        address: profile.address ?? '',
        city: profile.city ?? '',
        region: profile.region ?? '',
        phone: profile.phone ?? '',
        email: profile.email ?? '',
    };
}

/** An empty box clears the detail, which the API reads as null. */
function textOrNull(value: string): string | null {
    return value === '' ? null : value;
}

/** Turns the form into what the API takes. */
export function toProfileInput(values: ChurchProfileValues): ChurchProfileInput {
    return {
        name: values.name,
        denomination: textOrNull(values.denomination),
        address: textOrNull(values.address),
        city: textOrNull(values.city),
        region: textOrNull(values.region),
        phone: textOrNull(values.phone),
        email: textOrNull(values.email),
    };
}
