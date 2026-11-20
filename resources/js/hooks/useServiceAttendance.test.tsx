import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { LIVE_CHECKIN_POLL_MS } from '@/constants/polling';
import { useServiceAttendance } from '@/hooks/useServiceAttendance';
import { attendanceApi } from '@/services/api/attendance';
import { serviceAttendance } from '@/test/fixtures';

vi.mock('@/services/api/attendance');

function wrapper({ children }: { children: ReactNode }) {
    return (
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>{children}</QueryClientProvider>
    );
}

describe('useServiceAttendance', () => {
    beforeEach(() => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        vi.mocked(attendanceApi.forService).mockResolvedValue(serviceAttendance());
    });
    afterEach(() => vi.useRealTimers());

    it('asks again every ten seconds while the service is open', async () => {
        renderHook(() => useServiceAttendance('service-1', 1, true), { wrapper });
        await waitFor(() => expect(attendanceApi.forService).toHaveBeenCalledTimes(1));

        await vi.advanceTimersByTimeAsync(LIVE_CHECKIN_POLL_MS * 2 + 100);

        await waitFor(() => expect(vi.mocked(attendanceApi.forService).mock.calls.length).toBeGreaterThanOrEqual(3));
    });

    it('does not keep asking when the service is closed', async () => {
        renderHook(() => useServiceAttendance('service-1', 1, false), { wrapper });
        await waitFor(() => expect(attendanceApi.forService).toHaveBeenCalledTimes(1));

        await vi.advanceTimersByTimeAsync(LIVE_CHECKIN_POLL_MS * 3);

        expect(attendanceApi.forService).toHaveBeenCalledTimes(1);
    });

    it('polls every ten seconds, as decided in D-044', () => {
        expect(LIVE_CHECKIN_POLL_MS).toBe(10_000);
    });
});
