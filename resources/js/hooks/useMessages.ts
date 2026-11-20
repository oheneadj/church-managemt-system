import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { messagesApi } from '@/services/api/messages';
import type { MessageFilters } from '@/types/messages';

/** One page of the message log. The previous page stays on screen while the next loads. */
export function useMessages(filters: MessageFilters) {
    return useQuery({
        queryKey: QUERY_KEYS.messages.list(filters),
        queryFn: () => messagesApi.list(filters),
        placeholderData: keepPreviousData,
    });
}
