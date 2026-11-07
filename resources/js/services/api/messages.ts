import { api } from '@/services';
import type { Page } from '@/types/members';
import { IDEMPOTENCY_HEADER } from '@/lib/idempotencyKey';
import type { BroadcastEstimate, BroadcastInput, BroadcastQueued, MessageFilters, MessageLogLine } from '@/types/messages';

export const messagesApi = {
    /** One page of the church's message log, newest first, narrowed by the filters (docs/08, Get Message Log). */
    list: (filters: MessageFilters): Promise<Page<MessageLogLine>> =>
        api
            .get<Page<MessageLogLine>>('/messages', {
                params: {
                    page: filters.page,
                    ...(filters.channel === '' ? {} : { channel: filters.channel }),
                    ...(filters.type === '' ? {} : { message_type: filters.type }),
                    ...(filters.from === '' ? {} : { from: filters.from }),
                    ...(filters.to === '' ? {} : { to: filters.to }),
                },
            })
            .then((r) => r.data),

    /** What a broadcast would reach and cost. Nothing is queued, logged or charged. */
    estimate: (input: BroadcastInput): Promise<BroadcastEstimate> =>
        api.post<BroadcastEstimate>('/messages/broadcast/estimate', input).then((r) => r.data),

    /** Queues a broadcast. The key makes a double click or a retry send one broadcast, which matters because it costs credits. */
    send: (input: BroadcastInput, idempotencyKey: string): Promise<BroadcastQueued> =>
        api.post<BroadcastQueued>('/messages/broadcast', input, { headers: { [IDEMPOTENCY_HEADER]: idempotencyKey } }).then((r) => r.data),
};
