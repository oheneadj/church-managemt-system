import { describe, expect, it } from 'vitest';
import { blankFundValues, fundTypeSchema, toFundTypeInput, valuesFromFund } from '@/components/FundTypes/fundTypeSchema';
import { fundType } from '@/test/fixtures';

const valid = { name: 'Harvest', description: 'Giving at harvest time.', sort_order: '4' };

function problems(overrides: Record<string, string>): string[] {
    const result = fundTypeSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('fundTypeSchema', () => {
    it('accepts a name, a description and an order', () => {
        expect(problems({})).toEqual([]);
    });

    it('needs a name that is not only spaces, up to 100 characters', () => {
        expect(problems({ name: '   ' })).toEqual(['name']);
        expect(problems({ name: 'x'.repeat(100) })).toEqual([]);
        expect(problems({ name: 'x'.repeat(101) })).toEqual(['name']);
    });

    it('lets the description be empty, up to 2000 characters', () => {
        expect(problems({ description: '' })).toEqual([]);
        expect(problems({ description: 'x'.repeat(2001) })).toEqual(['description']);
    });

    it('keeps the order a whole number from 0 to 1000, and both ends are allowed', () => {
        expect(problems({ sort_order: '0' })).toEqual([]);
        expect(problems({ sort_order: '1000' })).toEqual([]);
        expect(problems({ sort_order: '1001' })).toEqual(['sort_order']);
        expect(problems({ sort_order: '-1' })).toEqual(['sort_order']);
        expect(problems({ sort_order: '2.5' })).toEqual(['sort_order']);
        expect(problems({ sort_order: '' })).toEqual(['sort_order']);
    });
});

describe('fund form values', () => {
    it('puts a new fund at the end of the list', () => {
        expect(blankFundValues([fundType({ sort_order: 0 }), fundType({ id: 'f2', sort_order: 6 })])).toEqual({
            name: '',
            description: '',
            sort_order: '7',
        });
        expect(blankFundValues([])).toEqual({ name: '', description: '', sort_order: '0' });
    });

    it('never starts a new fund past the highest order the server accepts', () => {
        expect(blankFundValues([fundType({ sort_order: 1000 })]).sort_order).toBe('1000');
    });

    it('fills the form from a saved fund, with no description as an empty box', () => {
        expect(valuesFromFund(fundType({ description: null, sort_order: 3 }))).toEqual({ name: 'Tithe', description: '', sort_order: '3' });
    });

    it('sends an empty description as null, which clears it, and the order as a number', () => {
        expect(toFundTypeInput({ name: 'Tithe', description: '', sort_order: '3' })).toEqual({
            name: 'Tithe',
            description: null,
            sort_order: 3,
        });
    });
});
