import { describe, expect, it } from 'vitest';
import { blankPledgeValues, pledgeFormSchema, toPledgeChange, valuesFromPledge } from '@/components/Giving/pledgeFormSchema';
import { pledge } from '@/test/fixtures';
import { isoToDateText } from '@/utils/dateText';
import { daysFromToday } from '@/utils/today';

const valid = {
    fund_type_id: 'fund-1',
    description: 'Building Fund pledge',
    amount_pledged: '1000.00',
    target_date: isoToDateText(daysFromToday(30)),
};

function problems(overrides: Record<string, string>, options: Parameters<typeof pledgeFormSchema>[0] = { requireFund: true }): string[] {
    const result = pledgeFormSchema(options).safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('pledgeFormSchema', () => {
    it('accepts a fund, a description, an amount and a future date', () => {
        expect(problems({})).toEqual([]);
    });

    it('needs a description, an amount and a date, and a fund when a new pledge is made', () => {
        expect(problems({ fund_type_id: '', description: '', amount_pledged: '', target_date: '' })).toEqual(
            expect.arrayContaining(['fund_type_id', 'description', 'amount_pledged', 'target_date']),
        );
    });

    it('does not ask for a fund when a pledge is only being changed', () => {
        expect(problems({ fund_type_id: '' }, {})).toEqual([]);
    });

    it('keeps the description to 255 characters', () => {
        expect(problems({ description: 'x'.repeat(256) })).toContain('description');
        expect(problems({ description: 'x'.repeat(255) })).toEqual([]);
    });

    it('refuses a target date in the past, and allows today', () => {
        expect(problems({ target_date: isoToDateText(daysFromToday(-1)) })).toContain('target_date');
        expect(problems({ target_date: isoToDateText(daysFromToday(0)) })).toEqual([]);
        expect(problems({ target_date: '31/02/2027' })).toContain('target_date');
    });

    it('lets a pledge keep its own old date when it is changed, but not move to another date in the past', () => {
        const past = daysFromToday(-20);

        expect(problems({ target_date: isoToDateText(past) }, { unchangedDate: past })).toEqual([]);
        expect(problems({ target_date: isoToDateText(daysFromToday(-5)) }, { unchangedDate: past })).toContain('target_date');
    });

    it('refuses an amount over the most a pledge may promise', () => {
        expect(problems({ amount_pledged: '1000000.00' })).toContain('amount_pledged');
        expect(problems({ amount_pledged: '999999.99' })).toEqual([]);
    });
});

describe('pledge form values', () => {
    it('starts a new pledge blank and fills the change form from a saved pledge, the date day first', () => {
        expect(blankPledgeValues()).toEqual({ fund_type_id: '', description: '', amount_pledged: '', target_date: '' });
        expect(valuesFromPledge(pledge())).toEqual({
            fund_type_id: 'fund-1',
            description: 'Building Fund 2026 pledge',
            amount_pledged: '1000.00',
            target_date: '31/12/2026',
        });
    });

    it('sends the date the way the API writes it', () => {
        expect(toPledgeChange({ ...valid, target_date: '31/12/2026' })).toEqual({
            description: 'Building Fund pledge',
            amount_pledged: '1000.00',
            target_date: '2026-12-31',
        });
    });
});
