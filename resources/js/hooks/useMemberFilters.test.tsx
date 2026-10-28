import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter } from 'react-router';
import { describe, expect, it } from 'vitest';
import { useMemberFilters } from '@/hooks/useMemberFilters';

function wrapperAt(url: string) {
    return function Wrapper({ children }: { children: ReactNode }) {
        return <MemoryRouter initialEntries={[url]}>{children}</MemoryRouter>;
    };
}

describe('useMemberFilters', () => {
    it('reads the filters and page from the address', () => {
        const { result } = renderHook(() => useMemberFilters(), {
            wrapper: wrapperAt('/members?search=abena&status=inactive&gender=female&department_id=d1&page=3'),
        });

        expect(result.current.filters).toEqual({ search: 'abena', status: 'inactive', gender: 'female', department_id: 'd1', page: 3 });
    });

    it('ignores values it does not recognise and bad page numbers', () => {
        const { result } = renderHook(() => useMemberFilters(), { wrapper: wrapperAt('/members?status=vanished&gender=robot&page=-4') });

        expect(result.current.filters).toMatchObject({ status: '', gender: '', page: 1 });
    });

    it('returns to page one when a filter changes, and drops an emptied filter', () => {
        const { result } = renderHook(() => useMemberFilters(), { wrapper: wrapperAt('/members?status=active&page=4') });

        act(() => result.current.setFilter('gender', 'female'));
        expect(result.current.filters).toMatchObject({ status: 'active', gender: 'female', page: 1 });

        act(() => result.current.setFilter('status', ''));
        expect(result.current.filters.status).toBe('');
    });

    it('changes only the page, and clears everything on request', () => {
        const { result } = renderHook(() => useMemberFilters(), { wrapper: wrapperAt('/members?search=abena') });

        act(() => result.current.setPage(2));
        expect(result.current.filters).toMatchObject({ search: 'abena', page: 2 });

        act(() => result.current.clear());
        expect(result.current.filters).toEqual({ search: '', status: '', gender: '', department_id: '', page: 1 });
    });
});
