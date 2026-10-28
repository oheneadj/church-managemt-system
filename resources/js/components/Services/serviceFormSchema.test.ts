import { describe, expect, it } from 'vitest';
import { serviceFormSchema, toServiceInput } from '@/components/Services/serviceFormSchema';

const valid = { name: 'Sunday Service', type: 'sunday', service_date: '2026-10-25', starts_at: '', ends_at: '', notes: '' };

function problems(overrides: Record<string, string>): string[] {
    const result = serviceFormSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('serviceFormSchema', () => {
    it('accepts a name, a type and a date', () => {
        expect(problems({})).toEqual([]);
    });

    it('needs a name and a date', () => {
        expect(problems({ name: '', service_date: '' })).toEqual(expect.arrayContaining(['name', 'service_date']));
    });

    it('rejects a type that does not exist', () => {
        expect(problems({ type: 'party' })).toContain('type');
    });

    it('wants the end after the start when both are given', () => {
        expect(problems({ starts_at: '11:00', ends_at: '09:00' })).toContain('ends_at');
        expect(problems({ starts_at: '09:00', ends_at: '11:00' })).toEqual([]);
        expect(problems({ starts_at: '09:00' })).toEqual([]);
    });

    it('leaves empty optional boxes out of the request', () => {
        const input = toServiceInput(serviceFormSchema.parse(valid));

        expect(input.starts_at).toBeUndefined();
        expect(input.notes).toBeUndefined();
        expect(input.name).toBe('Sunday Service');
    });
});
