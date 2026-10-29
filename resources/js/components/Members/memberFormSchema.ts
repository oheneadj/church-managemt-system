import { z } from 'zod';
import type { EducationalLevel, FamilyRole, Gender, MaritalStatus, MembershipType, MemberStatus } from '@/constants/enums';
import { EARLIEST_BIRTH_DATE } from '@/constants/dates';
import { PHONE_INPUT_PATTERN } from '@/constants/validation';
import type { Member, MemberInput } from '@/types/members';
import { dateBox } from '@/lib/dateBox';
import { dateTextToIso, isoToDateText } from '@/utils/dateText';
import { today } from '@/utils/today';

/** A box left empty is fine; if something is typed, it must pass the check. */
const emptyOr = (check: (text: string) => boolean, message: string) =>
    z
        .string()
        .trim()
        .refine((text) => text === '' || check(text), message);

/**
 * Every box of the add and edit form, as text exactly as the browser holds it. Optional boxes may be empty;
 * `toMemberInput` turns empty text into "nothing" before it is sent.
 */
export const memberFormSchema = z
    .object({
        // Step 1: who they are
        first_name: z.string().trim().min(1, 'Enter the first name').max(100, 'Keep it under 100 characters'),
        middle_name: z.string().trim().max(100, 'Keep it under 100 characters'),
        last_name: z.string().trim().min(1, 'Enter the last name').max(100, 'Keep it under 100 characters'),
        preferred_name: z.string().trim().max(100, 'Keep it under 100 characters'),
        gender: z.string().min(1, 'Choose a gender'),
        date_of_birth: dateBox({
            notBefore: { date: EARLIEST_BIRTH_DATE, message: 'The date of birth cannot be before 1900' },
            notAfter: { date: today, message: 'The date of birth cannot be in the future' },
        }),
        marital_status: z.string(),
        // Step 2: how to reach them
        phone: z.string().trim().min(1, 'Enter a phone number').regex(PHONE_INPUT_PATTERN, 'Enter a valid phone number'),
        phone_secondary: emptyOr((text) => PHONE_INPUT_PATTERN.test(text), 'Enter a valid phone number'),
        email: emptyOr((text) => z.email().safeParse(text).success, 'Enter a valid email address'),
        address: z.string().trim().max(1000, 'Keep it under 1000 characters'),
        nearest_landmark: z.string().trim().max(255, 'Keep it under 255 characters'),
        digital_address: z.string().trim().max(20, 'Keep it under 20 characters'),
        // Step 3: church life
        membership_type: z.string().min(1, 'Choose a membership type'),
        membership_date: dateBox({ notAfter: { date: today, message: 'The date they joined cannot be in the future' } }),
        status: z.string(),
        educational_level: z.string(),
        occupation: z.string().trim().max(255, 'Keep it under 255 characters'),
        tribe: z.string().trim().max(100, 'Keep it under 100 characters'),
        language_preference: z.string().trim().max(50, 'Keep it under 50 characters'),
        national_id_type: z.string().trim().max(50, 'Keep it under 50 characters'),
        national_id_number: z.string().trim().max(50, 'Keep it under 50 characters'),
        family_id: z.string(),
        family_role: z.string(),
        department_ids: z.array(z.string()),
        sms_opted_in: z.boolean(),
        whatsapp_opted_in: z.boolean(),
    })
    .refine((values) => values.family_id === '' || values.family_role !== '', {
        error: 'Choose their role in the family',
        path: ['family_role'],
    })
    .refine(
        (values) => {
            const born = dateTextToIso(values.date_of_birth);
            const joined = dateTextToIso(values.membership_date);

            return born === null || joined === null || joined >= born;
        },
        { error: 'The date they joined cannot be before their date of birth', path: ['membership_date'] },
    );

export type MemberFormValues = z.infer<typeof memberFormSchema>;
type FieldName = keyof MemberFormValues;

/** The three steps of the form and the boxes on each, so one step can be checked before moving on. */
export const FORM_STEPS: readonly { title: string; fields: readonly FieldName[] }[] = [
    {
        title: 'Who they are',
        fields: ['first_name', 'middle_name', 'last_name', 'preferred_name', 'gender', 'date_of_birth', 'marital_status'],
    },
    { title: 'How to reach them', fields: ['phone', 'phone_secondary', 'email', 'address', 'nearest_landmark', 'digital_address'] },
    {
        title: 'Church life',
        fields: [
            'membership_type',
            'membership_date',
            'status',
            'educational_level',
            'occupation',
            'tribe',
            'language_preference',
            'national_id_type',
            'national_id_number',
            'family_id',
            'family_role',
            'department_ids',
            'sms_opted_in',
            'whatsapp_opted_in',
        ],
    },
];

/** Which step a field is on, used to jump back to a step that has an error. */
export function stepOfField(field: string): number {
    const index = FORM_STEPS.findIndex((step) => (step.fields as readonly string[]).includes(field));

    return index === -1 ? 0 : index;
}

/** The form for a new member: empty, except for the choices most members start with. */
export function blankValues(): MemberFormValues {
    return {
        first_name: '',
        middle_name: '',
        last_name: '',
        preferred_name: '',
        gender: '',
        date_of_birth: '',
        marital_status: '',
        phone: '',
        phone_secondary: '',
        email: '',
        address: '',
        nearest_landmark: '',
        digital_address: '',
        membership_type: 'full',
        membership_date: '',
        status: 'active',
        educational_level: '',
        occupation: '',
        tribe: '',
        language_preference: '',
        national_id_type: '',
        national_id_number: '',
        family_id: '',
        family_role: '',
        department_ids: [],
        sms_opted_in: true,
        whatsapp_opted_in: false,
    };
}

/**
 * The form filled in from an existing member. The national ID number is never sent back by the server,
 * so those two boxes start empty and are only sent if the user types something.
 */
export function valuesFromMember(member: Member): MemberFormValues {
    return {
        ...blankValues(),
        first_name: member.first_name,
        middle_name: member.middle_name ?? '',
        last_name: member.last_name,
        preferred_name: member.preferred_name ?? '',
        gender: member.gender,
        date_of_birth: isoToDateText(member.date_of_birth ?? ''),
        marital_status: member.marital_status ?? '',
        phone: member.phone,
        phone_secondary: member.phone_secondary ?? '',
        email: member.email ?? '',
        address: member.address ?? '',
        nearest_landmark: member.nearest_landmark ?? '',
        digital_address: member.digital_address ?? '',
        membership_type: member.membership_type,
        membership_date: isoToDateText(member.membership_date ?? ''),
        status: member.status,
        educational_level: member.educational_level ?? '',
        occupation: member.occupation ?? '',
        tribe: member.tribe ?? '',
        language_preference: member.language_preference ?? '',
        family_id: member.family?.id ?? '',
        family_role: member.family?.role ?? '',
        department_ids: member.departments?.map((department) => department.id) ?? [],
        sms_opted_in: member.sms_opted_in,
        whatsapp_opted_in: member.whatsapp_opted_in,
    };
}

/** Empty text means "nothing", which the server stores as no value. */
function textOrNull(text: string): string | null {
    return text === '' ? null : text;
}

/**
 * Turns the form's text boxes into what the API expects: empty boxes become null, the choices get their proper
 * types, a new member also sends the starting status, and the national ID is only sent when typed.
 */
export function toMemberInput(values: MemberFormValues, editing: boolean): MemberInput {
    return {
        first_name: values.first_name,
        middle_name: textOrNull(values.middle_name),
        last_name: values.last_name,
        preferred_name: textOrNull(values.preferred_name),
        gender: values.gender as Gender,
        date_of_birth: dateTextToIso(values.date_of_birth),
        marital_status: textOrNull(values.marital_status) as MaritalStatus | null,
        phone: values.phone,
        phone_secondary: textOrNull(values.phone_secondary),
        email: textOrNull(values.email),
        address: textOrNull(values.address),
        nearest_landmark: textOrNull(values.nearest_landmark),
        digital_address: textOrNull(values.digital_address),
        membership_type: values.membership_type as MembershipType,
        membership_date: dateTextToIso(values.membership_date),
        educational_level: textOrNull(values.educational_level) as EducationalLevel | null,
        occupation: textOrNull(values.occupation),
        tribe: textOrNull(values.tribe),
        language_preference: textOrNull(values.language_preference),
        family_id: textOrNull(values.family_id),
        family_role: textOrNull(values.family_role) as FamilyRole | null,
        department_ids: values.department_ids,
        sms_opted_in: values.sms_opted_in,
        whatsapp_opted_in: values.whatsapp_opted_in,
        ...(editing ? {} : { status: values.status as MemberStatus }),
        ...(values.national_id_number === ''
            ? {}
            : { national_id_type: textOrNull(values.national_id_type), national_id_number: values.national_id_number }),
    };
}
