import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { messagesApi } from '@/services/api/messages';
import type { BroadcastInput } from '@/types/messages';

/**
 * What a broadcast would reach and cost, asked for as the text is typed. Nothing is asked for until there is a message,
 * and the last answer stays on screen while the next one loads, so the figures do not flicker.
 */
export function useBroadcastEstimate(input: BroadcastInput) {
    const key = { message: input.message, status: input.recipient_filter.status, department: input.recipient_filter.department_id ?? '' };

    return useQuery({
        queryKey: QUERY_KEYS.messages.estimate(key),
        queryFn: () => messagesApi.estimate(input),
        enabled: input.message.trim() !== '',
        placeholderData: keepPreviousData,
    });
}

/** Sending a broadcast. It refreshes the message log and the credit balance in the header, which the texts will use. */
export function useSendBroadcast() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: ({ input, idempotencyKey }: { input: BroadcastInput; idempotencyKey: string }) =>
            messagesApi.send(input, idempotencyKey),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: QUERY_KEYS.messages.all });
            void queryClient.invalidateQueries({ queryKey: QUERY_KEYS.church.credits });
        },
    });
}
