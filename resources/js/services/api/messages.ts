import { api } from '@/services';
import type { Page } from '@/types/members';
import type { MessageFilters, MessageLogLine } from '@/types/messages';

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
};
