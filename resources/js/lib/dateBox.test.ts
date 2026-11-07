import { describe, expect, it } from 'vitest';
import { dateBox } from '@/lib/dateBox';

/** The message for a value, or undefined when it passes. */
function messageFor(schema: ReturnType<typeof dateBox>, value: string): string | undefined {
    const result = schema.safeParse(value);

    return result.success ? undefined : result.error.issues[0]?.message;
}

describe('dateBox', () => {
    it('lets an optional box be empty', () => {
        expect(messageFor(dateBox(), '')).toBeUndefined();
        expect(messageFor(dateBox(), '   ')).toBeUndefined();
    });

    it('says what is missing in a required box', () => {
        expect(messageFor(dateBox({ required: 'Enter the date' }), '')).toBe('Enter the date');
    });

    it('wants a real, complete date as the date input gives it (year, month, day)', () => {
        const schema = dateBox();

        expect(messageFor(schema, '2026-12-25')).toBeUndefined();
        expect(messageFor(schema, '2024-02-29')).toBeUndefined();
        expect(messageFor(schema, '2026-12')).toBe('Enter a real date');
        expect(messageFor(schema, '2026-02-31')).toBe('Enter a real date');
        expect(messageFor(schema, '2025-02-29')).toBe('Enter a real date');
        expect(messageFor(schema, '2026-13-01')).toBe('Enter a real date');
        expect(messageFor(schema, '25/12/2026')).toBe('Enter a real date');
    });

    it('keeps to the limits, which are themselves allowed', () => {
        const schema = dateBox({
            notBefore: { date: '2026-01-10', message: 'Too early' },
            notAfter: { date: '2026-01-20', message: 'Too late' },
        });

        expect(messageFor(schema, '2026-01-09')).toBe('Too early');
        expect(messageFor(schema, '2026-01-10')).toBeUndefined();
        expect(messageFor(schema, '2026-01-20')).toBeUndefined();
        expect(messageFor(schema, '2026-01-21')).toBe('Too late');
    });
});
