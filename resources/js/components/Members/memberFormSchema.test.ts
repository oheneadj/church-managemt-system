import { describe, expect, it } from 'vitest';
import {
    blankValues,
    FORM_STEPS,
    memberFormSchema,
    stepOfField,
    toMemberInput,
    valuesFromMember,
} from '@/components/Members/memberFormSchema';
import { member } from '@/test/fixtures';

const valid = { ...blankValues(), first_name: 'Abena', last_name: 'Asante', gender: 'female', phone: '0200987654' };

function messageFor(overrides: Partial<ReturnType<typeof blankValues>>, field: string): string | undefined {
    const result = memberFormSchema.safeParse({ ...valid, ...overrides });

    return result.success ? undefined : result.error.issues.find((issue) => issue.path[0] === field)?.message;
}

describe('memberFormSchema', () => {
    it('accepts the minimum a member needs', () => {
        expect(memberFormSchema.safeParse(valid).success).toBe(true);
    });

    it('asks for the first name, last name, gender and phone', () => {
        const result = memberFormSchema.safeParse(blankValues());
        const missing = result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));

        expect(missing).toEqual(expect.arrayContaining(['first_name', 'last_name', 'gender', 'phone']));
    });

    it('checks the phone, email and date of birth only when something is typed', () => {
        expect(messageFor({ phone: 'abc' }, 'phone')).toBe('Enter a valid phone number');
        expect(messageFor({ phone_secondary: '12' }, 'phone_secondary')).toBe('Enter a valid phone number');
        expect(messageFor({ phone_secondary: '' }, 'phone_secondary')).toBeUndefined();
        expect(messageFor({ email: 'nope' }, 'email')).toBe('Enter a valid email address');
        expect(messageFor({ email: '' }, 'email')).toBeUndefined();
        expect(messageFor({ date_of_birth: '2999-01-01' }, 'date_of_birth')).toBe('The date of birth cannot be in the future');
        expect(messageFor({ date_of_birth: '1985-07-12' }, 'date_of_birth')).toBeUndefined();
    });

    it('keeps dates real and inside their limits', () => {
        expect(messageFor({ date_of_birth: '1899-12-31' }, 'date_of_birth')).toBe('The date of birth cannot be before 1900');
        expect(messageFor({ date_of_birth: '1900-01-01' }, 'date_of_birth')).toBeUndefined();
        expect(messageFor({ date_of_birth: '1990-02-31' }, 'date_of_birth')).toBe('Enter a real date');
        expect(messageFor({ date_of_birth: '01/02/1990' }, 'date_of_birth')).toBe('Enter a real date');
        expect(messageFor({ membership_date: '2999-01-01' }, 'membership_date')).toBe('The date they joined cannot be in the future');
        expect(messageFor({ membership_date: '2001-06-01' }, 'membership_date')).toBeUndefined();
        expect(messageFor({ membership_date: '' }, 'membership_date')).toBeUndefined();
    });

    it('does not let someone join before they were born, and takes the day they were born', () => {
        expect(messageFor({ date_of_birth: '2000-05-10', membership_date: '2000-05-09' }, 'membership_date')).toBe(
            'The date they joined cannot be before their date of birth',
        );
        expect(messageFor({ date_of_birth: '2000-05-10', membership_date: '2000-05-10' }, 'membership_date')).toBeUndefined();
        expect(messageFor({ date_of_birth: '', membership_date: '1950-01-01' }, 'membership_date')).toBeUndefined();
    });

    it('wants a role whenever a family is chosen', () => {
        expect(messageFor({ family_id: 'family-1', family_role: '' }, 'family_role')).toBe('Choose their role in the family');
        expect(messageFor({ family_id: 'family-1', family_role: 'child' }, 'family_role')).toBeUndefined();
        expect(messageFor({ family_id: '', family_role: '' }, 'family_role')).toBeUndefined();
    });
});

describe('form steps', () => {
    it('cover every box exactly once', () => {
        const fields = FORM_STEPS.flatMap((step) => step.fields);

        expect([...fields].sort()).toEqual(Object.keys(blankValues()).sort());
    });

    it('find the step a box is on, falling back to the first', () => {
        expect(stepOfField('first_name')).toBe(0);
        expect(stepOfField('phone')).toBe(1);
        expect(stepOfField('family_role')).toBe(2);
        expect(stepOfField('somethingElse')).toBe(0);
    });
});

describe('toMemberInput', () => {
    it("sends empty optional boxes as null and a new member's starting status", () => {
        const input = toMemberInput(valid, false);

        expect(input).toMatchObject({
            first_name: 'Abena',
            middle_name: null,
            email: null,
            family_id: null,
            status: 'active',
            membership_type: 'full',
            sms_opted_in: true,
        });
    });

    it('sends the dates the way the API writes them, and an empty date as null', () => {
        const input = toMemberInput({ ...valid, date_of_birth: '1985-07-12', membership_date: '' }, false);

        expect(input.date_of_birth).toBe('1985-07-12');
        expect(input.membership_date).toBeNull();
    });

    it('leaves the status out when editing, because status has its own endpoint', () => {
        expect(toMemberInput(valid, true)).not.toHaveProperty('status');
    });

    it('sends the national ID only when one was typed', () => {
        expect(toMemberInput(valid, false)).not.toHaveProperty('national_id_number');
        expect(toMemberInput({ ...valid, national_id_type: 'Ghana Card', national_id_number: 'GHA-123' }, false)).toMatchObject({
            national_id_type: 'Ghana Card',
            national_id_number: 'GHA-123',
        });
    });
});

describe('valuesFromMember', () => {
    it('fills the form from the saved member, with empty text for anything missing', () => {
        const values = valuesFromMember(
            member({
                family: { id: 'f1', name: 'Mensah Family', role: 'head', role_label: 'Head' },
                departments: [{ id: 'd1', name: 'Choir', role: null }],
            }),
        );

        expect(values).toMatchObject({
            first_name: 'Emmanuel',
            middle_name: '',
            date_of_birth: '1985-07-12',
            family_id: 'f1',
            family_role: 'head',
            department_ids: ['d1'],
            national_id_number: '',
        });
    });
});
