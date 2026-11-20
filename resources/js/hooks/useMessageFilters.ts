import { useSearchParams } from 'react-router';
import { MESSAGE_CHANNELS } from '@/constants/enums';
import { MESSAGE_TYPES } from '@/constants/messages';
import type { MessageFilters } from '@/types/messages';

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/** A date from the address bar, or empty when it is missing or not a date. */
function dateParam(value: string | null): string {
    return value !== null && ISO_DATE.test(value) ? value : '';
}

/**
 * The message log's filters and page, kept in the address bar so a refresh and a shared link show the same list. A value
 * that is not valid is ignored. Changing a filter returns to page 1.
 */
export function useMessageFilters() {
    const [params, setParams] = useSearchParams();

    const filters: MessageFilters = {
        channel: MESSAGE_CHANNELS.find((option) => option.value === params.get('channel'))?.value ?? '',
        type: MESSAGE_TYPES.find((option) => option.value === params.get('type'))?.value ?? '',
        from: dateParam(params.get('from')),
        to: dateParam(params.get('to')),
        page: Math.max(1, Number(params.get('page')) || 1),
    };

    function setFilter(name: 'channel' | 'type' | 'from' | 'to', value: string) {
        setParams((current) => {
            if (value === '') current.delete(name);
            else current.set(name, value);
            current.delete('page');

            return current;
        });
    }

    function setPage(page: number) {
        setParams((current) => {
            current.set('page', String(page));

            return current;
        });
    }

    return { filters, setFilter, setPage };
}
