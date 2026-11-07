import { describe, expect, it } from 'vitest';
import { contributionFilterParams, memberFilterParams } from '@/services/api/filterParams';

describe('memberFilterParams', () => {
    it('leaves out every empty filter, and the page', () => {
        expect(memberFilterParams({ search: '', status: '', gender: '', department_id: '', page: 3 })).toEqual({});
    });

    it('names each filter the way the API does', () => {
        expect(memberFilterParams({ search: 'Abena', status: 'active', gender: 'female', department_id: 'dept-1', page: 1 })).toEqual({
            search: 'Abena',
            status: 'active',
            gender: 'female',
            department_id: 'dept-1',
        });
    });
});

describe('contributionFilterParams', () => {
    it('leaves out every empty filter, the page and the voided switch', () => {
        expect(contributionFilterParams({ from: '', to: '', fund: '', method: '', voided: true, page: 2 })).toEqual({});
    });

    it('names each filter the way the API does', () => {
        expect(
            contributionFilterParams({ from: '2026-10-01', to: '2026-10-31', fund: 'fund-1', method: 'cash', voided: false, page: 1 }),
        ).toEqual({
            from: '2026-10-01',
            to: '2026-10-31',
            fund_type_id: 'fund-1',
            payment_method: 'cash',
        });
    });
});
