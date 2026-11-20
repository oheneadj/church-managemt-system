import { describe, expect, it } from 'vitest';
import { churchSettingsSchema, toSettingsInput, valuesFromSettings } from '@/components/Settings/churchSettingsSchema';
import { churchSettings } from '@/test/fixtures';

const valid = valuesFromSettings(churchSettings());

function problems(overrides: Record<string, string | boolean>): string[] {
    const result = churchSettingsSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('churchSettingsSchema', () => {
    it('accepts the saved settings', () => {
        expect(problems({})).toEqual([]);
    });

    it('keeps the youth age from 10 to 40, and both ends are allowed', () => {
        expect(problems({ youth_age_limit: '10' })).toEqual([]);
        expect(problems({ youth_age_limit: '40' })).toEqual([]);
        expect(problems({ youth_age_limit: '9' })).toEqual(['youth_age_limit']);
        expect(problems({ youth_age_limit: '41' })).toEqual(['youth_age_limit']);
    });

    it('keeps the absence number from 2 to 12, and both ends are allowed', () => {
        expect(problems({ absence_threshold: '2' })).toEqual([]);
        expect(problems({ absence_threshold: '12' })).toEqual([]);
        expect(problems({ absence_threshold: '1' })).toEqual(['absence_threshold']);
        expect(problems({ absence_threshold: '13' })).toEqual(['absence_threshold']);
    });

    it('wants whole numbers', () => {
        expect(problems({ youth_age_limit: '' })).toEqual(['youth_age_limit']);
        expect(problems({ youth_age_limit: '25.5' })).toEqual(['youth_age_limit']);
        expect(problems({ absence_threshold: '-3' })).toEqual(['absence_threshold']);
    });
});

describe('settings form values', () => {
    it('turns the settings into boxes and back', () => {
        const saved = churchSettings({ youth_age_limit: 30, absent_alert: true });

        expect(valuesFromSettings(saved)).toEqual({
            youth_age_limit: '30',
            absence_threshold: '3',
            receipt_on_cash: true,
            absent_alert: true,
        });
        expect(toSettingsInput(valuesFromSettings(saved))).toEqual(saved);
    });
});
