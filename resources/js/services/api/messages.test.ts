import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/services';
import { messagesApi } from '@/services/api/messages';
import { messageLogLine, pageOf } from '@/test/fixtures';

vi.mock('@/services', () => ({ api: { get: vi.fn(), post: vi.fn() } }));

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

const input = { channel: 'sms', message: 'Hello', recipient_filter: { status: 'active' } } as const;

describe('messagesApi.estimate and send', () => {
    it('asks what a broadcast would cost without a key, because nothing is recorded', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: { recipients: 3 } });

        await expect(messagesApi.estimate(input)).resolves.toEqual({ recipients: 3 });
        expect(api.post).toHaveBeenCalledWith('/messages/broadcast/estimate', input);
    });

    it('sends a broadcast with the key, so a retry sends it once', async () => {
        vi.mocked(api.post).mockResolvedValue({ data: { queued: true } });

        await messagesApi.send(input, 'key-1');

        expect(api.post).toHaveBeenCalledWith('/messages/broadcast', input, { headers: { 'Idempotency-Key': 'key-1' } });
    });
});
