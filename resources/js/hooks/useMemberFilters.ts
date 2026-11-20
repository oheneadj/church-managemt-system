import { useSearchParams } from 'react-router';
import { MEMBER_STATUSES, GENDERS, type Gender, type MemberStatus } from '@/constants/enums';
import type { MemberFilters } from '@/types/members';

/** Picks the value if it is one of the allowed ones, otherwise an empty filter. */
function oneOf<V extends string>(value: string | null, allowed: readonly { value: V }[]): V | '' {
    return allowed.find((option) => option.value === value)?.value ?? '';
}

/**
 * The directory filters, kept in the URL so a refresh, the back button and a shared link all keep
 * the same view (docs/patterns state management). Unknown values in the URL are ignored.
 * Changing any filter returns to page 1.
 */
export function useMemberFilters() {
    const [params, setParams] = useSearchParams();

    const filters: MemberFilters = {
        search: params.get('search') ?? '',
        status: oneOf<MemberStatus>(params.get('status'), MEMBER_STATUSES),
        gender: oneOf<Gender>(params.get('gender'), GENDERS),
        department_id: params.get('department_id') ?? '',
        page: Math.max(1, Number(params.get('page')) || 1),
    };

    function setFilter(name: keyof Omit<MemberFilters, 'page'>, value: string) {
        setParams((current) => {
            if (value === '') current.delete(name);
            else current.set(name, value);
            current.delete('page');

            return current;
        });
    }

    function clear() {
        setParams({});
    }

    function setPage(page: number) {
        setParams((current) => {
            current.set('page', String(page));

            return current;
        });
    }

    return { filters, setFilter, setPage, clear };
}
