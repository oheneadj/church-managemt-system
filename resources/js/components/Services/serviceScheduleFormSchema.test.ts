import { describe, expect, it } from 'vitest';
import {
    blankScheduleValues,
    serviceScheduleFormSchema,
    toScheduleInput,
    valuesFromSchedule,
} from '@/components/Services/serviceScheduleFormSchema';
import { serviceSchedule } from '@/test/fixtures';

const valid = { name: 'Sunday Morning Service', type: 'sunday', weekday: 'sunday', starts_at: '', ends_at: '' };

function problems(overrides: Record<string, string>): string[] {
    const result = serviceScheduleFormSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('serviceScheduleFormSchema', () => {
    it('accepts a name, a type and a day, with or without times', () => {
        expect(problems({})).toEqual([]);
        expect(problems({ starts_at: '09:00', ends_at: '11:30' })).toEqual([]);
    });

    it('needs a name', () => {
        expect(problems({ name: '   ' })).toContain('name');
    });

    it('rejects a type or a day that does not exist', () => {
        expect(problems({ type: 'party' })).toContain('type');
        expect(problems({ weekday: 'someday' })).toContain('weekday');
    });

    it('wants the end after the start when both are given', () => {
        expect(problems({ starts_at: '11:00', ends_at: '09:00' })).toContain('ends_at');
        expect(problems({ starts_at: '09:00' })).toEqual([]);
    });
});

describe('the form values and the request', () => {
    it('starts as a Sunday service', () => {
        expect(blankScheduleValues()).toEqual({ name: '', type: 'sunday', weekday: 'sunday', starts_at: '', ends_at: '' });
    });

    it('is filled in from a saved schedule, with empty text for a missing time', () => {
        expect(valuesFromSchedule(serviceSchedule({ starts_at: null, ends_at: null, weekday: 'friday', name: 'Youth' }))).toEqual({
            name: 'Youth',
            type: 'sunday',
            weekday: 'friday',
            starts_at: '',
            ends_at: '',
        });
    });

    it('sends an empty time as null, so a time can be cleared', () => {
        const input = toScheduleInput(serviceScheduleFormSchema.parse(valid));

        expect(input).toEqual({ name: 'Sunday Morning Service', type: 'sunday', weekday: 'sunday', starts_at: null, ends_at: null });
        expect(toScheduleInput(serviceScheduleFormSchema.parse({ ...valid, starts_at: '09:00' })).starts_at).toBe('09:00');
    });
});
