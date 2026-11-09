import { describe, expect, it } from 'vitest';
import { blankPackageValues, smsPackageSchema, toPackageInput, valuesFromPackage } from '@/components/Platform/smsPackageSchema';
import { smsPackage } from '@/test/fixtures';

const valid = { name: 'Starter', credits: '100', price: '12.50', sort_order: '0' };

function problems(overrides: Record<string, string>): string[] {
    const result = smsPackageSchema.safeParse({ ...valid, ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('smsPackageSchema', () => {
    it('accepts a name, credits, a price and an order', () => {
        expect(problems({})).toEqual([]);
    });

    it('needs a name, up to 100 characters', () => {
        expect(problems({ name: '  ' })).toEqual(['name']);
        expect(problems({ name: 'x'.repeat(101) })).toEqual(['name']);
    });

    it('wants whole credits from 1 to a million, both ends allowed', () => {
        expect(problems({ credits: '1' })).toEqual([]);
        expect(problems({ credits: '1000000' })).toEqual([]);
        for (const bad of ['0', '1000001', '1.5', '', '-3']) {
            expect(problems({ credits: bad })).toEqual(['credits']);
        }
    });

    it('wants a price above 0 with at most two decimals and at most 99,999.99, read as exact text', () => {
        for (const good of ['0.01', '7', '1234.5', '99999.99']) {
            expect(problems({ price: good })).toEqual([]);
        }
        for (const bad of ['0', '0.00', '-5', '10.001', '100000.00', 'ten', '', '1e3']) {
            expect(problems({ price: bad })).toEqual(['price']);
        }
    });

    it('keeps the order a whole number from 0 to 1000', () => {
        expect(problems({ sort_order: '1000' })).toEqual([]);
        expect(problems({ sort_order: '1001' })).toEqual(['sort_order']);
        expect(problems({ sort_order: '-1' })).toEqual(['sort_order']);
    });
});

describe('package form values', () => {
    it('puts a new package at the end of the list', () => {
        expect(blankPackageValues([smsPackage({ sort_order: 2 }), smsPackage({ id: 'p2', sort_order: 5 })]).sort_order).toBe('6');
        expect(blankPackageValues([])).toEqual({ name: '', credits: '', price: '', sort_order: '0' });
    });

    it('fills the form from a saved package, and sends the price as the text that was typed', () => {
        expect(valuesFromPackage(smsPackage())).toEqual({ name: 'Starter 100', credits: '100', price: '12.50', sort_order: '0' });
        expect(toPackageInput({ name: 'A', credits: '50', price: '7', sort_order: '3' })).toEqual({
            name: 'A',
            credits: 50,
            price: '7',
            sort_order: 3,
        });
    });
});
