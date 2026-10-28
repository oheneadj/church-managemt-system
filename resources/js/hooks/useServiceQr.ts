import { useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { servicesApi } from '@/services/api/services';

/**
 * The code for the QR display. It asks for a new one as often as the server says (`refresh_in`), which is shorter than
 * the time a code lasts, so the code on the screen always has time left. It stops when check-in is closed.
 */
export function useServiceQr(serviceId: string) {
    return useQuery({
        queryKey: QUERY_KEYS.services.qr(serviceId),
        queryFn: () => servicesApi.qr(serviceId),
        refetchInterval: (query) => (query.state.data ? query.state.data.refresh_in * 1000 : false),
        // A closed service answers 409 and retrying would not help.
        retry: false,
    });
}
