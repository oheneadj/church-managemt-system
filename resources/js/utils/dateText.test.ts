import { describe, expect, it } from 'vitest';
import { dateTextToIso, isoToDateText, maskDateText } from '@/utils/dateText';

describe('maskDateText', () => {
    it('puts the slashes in as digits are typed', () => {
        expect(maskDateText('2')).toBe('2');
        expect(maskDateText('25')).toBe('25');
        expect(maskDateText('251')).toBe('25/1');
        expect(maskDateText('2512')).toBe('25/12');
        expect(maskDateText('25122')).toBe('25/12/2');
        expect(maskDateText('25122026')).toBe('25/12/2026');
    });

    it('keeps its own slashes, drops anything else and stops at eight digits', () => {
        expect(maskDateText('25/12/2026')).toBe('25/12/2026');
        expect(maskDateText('25-12-2026')).toBe('25/12/2026');
        expect(maskDateText('25122026999')).toBe('25/12/2026');
        expect(maskDateText('ab')).toBe('');
        expect(maskDateText('')).toBe('');
    });

    it('does not leave a slash behind when the last digit after it is deleted', () => {
        expect(maskDateText('25/')).toBe('25');
        expect(maskDateText('25/12/')).toBe('25/12');
    });
});

describe('isoToDateText', () => {
    it('shows the day first', () => {
        expect(isoToDateText('2026-12-25')).toBe('25/12/2026');
        expect(isoToDateText('1985-07-02')).toBe('02/07/1985');
    });

    it('is empty for anything that is not a date', () => {
        expect(isoToDateText('')).toBe('');
        expect(isoToDateText('soon')).toBe('');
        expect(isoToDateText('25/12/2026')).toBe('');
    });
});

describe('dateTextToIso', () => {
    it('reads a day-first date', () => {
        expect(dateTextToIso('25/12/2026')).toBe('2026-12-25');
        expect(dateTextToIso('02/07/1985')).toBe('1985-07-02');
        expect(dateTextToIso(' 02/07/1985 ')).toBe('1985-07-02');
    });

    it('is the other way round of isoToDateText', () => {
        expect(dateTextToIso(isoToDateText('2024-02-29'))).toBe('2024-02-29');
    });

    it('is null for a date that is not complete or not a real day', () => {
        expect(dateTextToIso('')).toBeNull();
        expect(dateTextToIso('25/12')).toBeNull();
        expect(dateTextToIso('25/12/26')).toBeNull();
        expect(dateTextToIso('31/02/2026')).toBeNull();
        expect(dateTextToIso('29/02/2025')).toBeNull();
        expect(dateTextToIso('00/12/2026')).toBeNull();
        expect(dateTextToIso('25/13/2026')).toBeNull();
        expect(dateTextToIso('2026-12-25')).toBeNull();
    });

    it('takes a leap day in a leap year', () => {
        expect(dateTextToIso('29/02/2024')).toBe('2024-02-29');
    });
});
