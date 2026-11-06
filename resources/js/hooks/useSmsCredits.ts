import { useQuery } from '@tanstack/react-query';
import { CREDITS_REFRESH_MS } from '@/constants/credits';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { authApi } from '@/services/api/auth';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * The church's SMS credit balance. The session holds the balance from sign-in, which goes stale as receipts use credits, so
 * it is shown straight away and then asked for again from the server (the same "who am I" call that restores a session),
 * on a timer while the page is open and whenever the window comes back into focus. If the server cannot be reached the
 * last known balance stays on screen.
 */
export function useSmsCredits(): number {
    const fromSession = useChurchStore((state) => state.church?.sms_credits ?? 0);
    const { data } = useQuery({
        queryKey: QUERY_KEYS.church.credits,
        queryFn: () => authApi.me().then((session) => session.church.sms_credits),
        placeholderData: fromSession,
        refetchInterval: CREDITS_REFRESH_MS,
    });

    return data ?? fromSession;
}
