import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { messagesApi } from '@/services/api/messages';
import { messageLogLine, pageOf } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn() } }));

describe('messagesApi.list', () => {
    beforeEach(() => {
        vi.mocked(api.get).mockResolvedValue({ data: pageOf([messageLogLine()]) });
    });

    it('asks only for the page when there are no filters', async () => {
        await messagesApi.list({ channel: '', type: '', from: '', to: '', page: 2 });

        expect(api.get).toHaveBeenCalledWith('/messages', { params: { page: 2 } });
    });

    it('sends each filter under the name the API uses', async () => {
        await messagesApi.list({ channel: 'sms', type: 'broadcast', from: '2026-11-01', to: '2026-11-30', page: 1 });

        expect(api.get).toHaveBeenCalledWith('/messages', {
            params: { page: 1, channel: 'sms', message_type: 'broadcast', from: '2026-11-01', to: '2026-11-30' },
        });
    });
});
