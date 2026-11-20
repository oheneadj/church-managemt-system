import { describe, expect, it } from 'vitest';
import { churchProfileSchema, toProfileInput, valuesFromProfile } from '@/components/Settings/churchProfileSchema';
import { churchProfile } from '@/test/fixtures';

const valid = valuesFromProfile(churchProfile());

function problems(overrides: Record<string, string>): string[] {
    const result = churchProfileSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('churchProfileSchema', () => {
    it('accepts the saved details', () => {
        expect(problems({})).toEqual([]);
    });

    it('needs a name, and nothing else', () => {
        expect(problems({ name: '  ' })).toEqual(['name']);
        expect(problems({ denomination: '', address: '', city: '', region: '', phone: '', email: '' })).toEqual([]);
    });

    it('checks the phone and the email only when they are given', () => {
        expect(problems({ phone: '12' })).toEqual(['phone']);
        expect(problems({ email: 'not-an-email' })).toEqual(['email']);
        expect(problems({ phone: '0302111222', email: 'Office@Grace.example' })).toEqual([]);
    });

    it('keeps each detail within the length the server allows', () => {
        expect(problems({ name: 'x'.repeat(256) })).toEqual(['name']);
        expect(problems({ city: 'x'.repeat(101) })).toEqual(['city']);
        expect(problems({ address: 'x'.repeat(1001) })).toEqual(['address']);
    });
});

describe('profile form values', () => {
    it('shows a detail the church never gave as an empty box', () => {
        expect(valuesFromProfile(churchProfile({ denomination: null, phone: null })).denomination).toBe('');
        expect(valuesFromProfile(churchProfile({ denomination: null, phone: null })).phone).toBe('');
    });

    it('sends an empty box as null, which clears the detail', () => {
        expect(toProfileInput({ ...valid, denomination: '', phone: '' })).toEqual({
            name: 'Grace Assembly',
            denomination: null,
            address: '12 Ring Road',
            city: 'Kumasi',
            region: 'Ashanti',
            phone: null,
            email: 'office@grace.example',
        });
    });
});
