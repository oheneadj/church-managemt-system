import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useServiceQr } from '@/hooks/useServiceQr';
import { servicesApi } from '@/services/api/services';
import { qrCode } from '@/test/fixtures';

vi.mock('@/services/api/services');

function wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={new QueryClient()}>{children}</QueryClientProvider>;
}

describe('useServiceQr', () => {
    beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }));
    afterEach(() => vi.useRealTimers());

    it('asks for a new code as often as the server says, so the code on screen never runs out', async () => {
        vi.mocked(servicesApi.qr).mockResolvedValue(qrCode({ refresh_in: 30 }));
        renderHook(() => useServiceQr('service-1'), { wrapper });
        await waitFor(() => expect(servicesApi.qr).toHaveBeenCalledTimes(1));

        await vi.advanceTimersByTimeAsync(30_100);

        await waitFor(() => expect(vi.mocked(servicesApi.qr).mock.calls.length).toBeGreaterThanOrEqual(2));
    });

    it('does not keep asking when the first answer is an error, such as a closed service', async () => {
        vi.mocked(servicesApi.qr).mockRejectedValue(new Error('closed'));
        renderHook(() => useServiceQr('service-1'), { wrapper });
        await waitFor(() => expect(servicesApi.qr).toHaveBeenCalledTimes(1));

        await vi.advanceTimersByTimeAsync(120_000);

        expect(servicesApi.qr).toHaveBeenCalledTimes(1);
    });
});
