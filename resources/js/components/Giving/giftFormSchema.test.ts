import { describe, expect, it } from 'vitest';
import { blankGiftValues, giftFormSchema, toGiftInput } from '@/components/Giving/giftFormSchema';
import { isoToDateText } from '@/utils/dateText';
import { daysFromToday, today } from '@/utils/today';

const valid = { ...blankGiftValues(isoToDateText(today())), fund_type_id: 'fund-1', amount: '150.00' };

function problems(overrides: Record<string, string>): string[] {
    const result = giftFormSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('giftFormSchema', () => {
    it('accepts a fund, an amount and today', () => {
        expect(problems({})).toEqual([]);
    });

    it('needs a fund, an amount and a date', () => {
        expect(problems({ fund_type_id: '', amount: '', contributed_on: '' })).toEqual(
            expect.arrayContaining(['fund_type_id', 'amount', 'contributed_on']),
        );
    });

    it.each(['0', '0.00', '-5', 'abc', '10.123', '1,000'])('rejects the amount "%s"', (amount) => {
        expect(problems({ amount })).toContain('amount');
    });

    it.each(['1', '0.01', '200.5', '999999.99'])('accepts the amount "%s"', (amount) => {
        expect(problems({ amount })).toEqual([]);
    });

    it('refuses an amount over the most one gift may be, by a single pesewa', () => {
        expect(problems({ amount: '1000000.00' })).toContain('amount');
        expect(problems({ amount: '999999.995' })).toContain('amount');
    });

    it('does not let a gift be dated in the future, and allows any past day', () => {
        expect(problems({ contributed_on: isoToDateText(daysFromToday(1)) })).toContain('contributed_on');
        expect(problems({ contributed_on: isoToDateText(daysFromToday(-400)) })).toEqual([]);
        expect(problems({ contributed_on: '31/02/2026' })).toContain('contributed_on');
    });

    it('keeps notes under 2000 characters', () => {
        expect(problems({ notes: 'x'.repeat(2001) })).toContain('notes');
    });
});

describe('toGiftInput', () => {
    it('sends a cash gift with the amount exactly as typed', () => {
        const input = toGiftInput(giftFormSchema.parse(valid), 'member-1', 'service-1');

        expect(input).toMatchObject({
            member_id: 'member-1',
            service_id: 'service-1',
            fund_type_id: 'fund-1',
            amount: '150.00',
            payment_method: 'cash',
        });
    });

    it('names the pledge the gift pays, or leaves it out', () => {
        expect(toGiftInput(giftFormSchema.parse(valid), 'member-1', undefined, 'pledge-1').pledge_id).toBe('pledge-1');
        expect(toGiftInput(giftFormSchema.parse(valid), 'member-1', undefined, undefined).pledge_id).toBeUndefined();
    });

    it('leaves out the member, service and notes when there are none, which makes an anonymous gift', () => {
        const input = toGiftInput(giftFormSchema.parse(valid), undefined, undefined);

        expect(input.member_id).toBeUndefined();
        expect(input.service_id).toBeUndefined();
        expect(input.notes).toBeUndefined();
    });

    it('sends no time for a gift given today so the server uses the moment it was entered', () => {
        expect(toGiftInput(giftFormSchema.parse(valid), undefined, undefined).contributed_at).toBeUndefined();
    });

    it('sends an earlier day at noon UTC', () => {
        const past = daysFromToday(-7);
        const input = toGiftInput(giftFormSchema.parse({ ...valid, contributed_on: isoToDateText(past) }), undefined, undefined);

        expect(input.contributed_at).toBe(`${past}T12:00:00Z`);
    });
});
