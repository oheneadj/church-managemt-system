import { describe, expect, it } from 'vitest';
import { blankStartChurchValues, startChurchSchema, toStartChurchInput } from '@/components/Onboarding/startChurchSchema';

const valid = {
    ...blankStartChurchValues,
    church_name: 'Victory Chapel',
    member_number_prefix: 'vci',
    admin_name: 'Kofi Mensah',
    admin_phone: '024 430 0001',
    otp: '123456',
};

function errorsOf(overrides: Partial<typeof valid>): string[] {
    const result = startChurchSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('startChurchSchema', () => {
    it('accepts the smallest valid sign-up, with the web address left empty', () => {
        expect(errorsOf({})).toEqual([]);
    });

    it('requires the name, prefix, admin name, phone and code', () => {
        expect(errorsOf({ church_name: '', member_number_prefix: '', admin_name: '', admin_phone: '', otp: '' })).toEqual(
            expect.arrayContaining(['church_name', 'member_number_prefix', 'admin_name', 'admin_phone', 'otp']),
        );
    });

    it.each(['Grace Assembly!!', 'grace_assembly', 'grace--assembly', '-grace', 'ab', 'a'.repeat(41)])(
        'rejects the web address "%s"',
        (slug) => {
            expect(errorsOf({ slug })).toContain('slug');
        },
    );

    it('treats capitals in the web address as lowercase', () => {
        expect(errorsOf({ slug: 'Victory-Chapel' })).toEqual([]);
    });

    it.each(['G', 'ABCDEFG', 'G12', 'GR-C'])('rejects the prefix "%s"', (prefix) => {
        expect(errorsOf({ member_number_prefix: prefix })).toContain('member_number_prefix');
    });

    it('wants exactly six digits for the code', () => {
        expect(errorsOf({ otp: '12345' })).toContain('otp');
        expect(errorsOf({ otp: '12345a' })).toContain('otp');
    });

    it('leaves empty optional boxes out of the request', () => {
        const input = toStartChurchInput(valid);

        expect(input.slug).toBeUndefined();
        expect(input.denomination).toBeUndefined();
        expect(input.city).toBeUndefined();
        expect(input.member_number_prefix).toBe('vci');
    });
});
