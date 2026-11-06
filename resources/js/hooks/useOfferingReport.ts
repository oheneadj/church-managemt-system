import { useQuery } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { offeringReportApi } from '@/services/api/offeringReport';

/**
 * The offering report of one service. It sits under the services keys, so recording or voiding a gift (which
 * refreshes those) refreshes an open report too.
 */
export function useOfferingReport(serviceId: string) {
    return useQuery({
        queryKey: QUERY_KEYS.services.offeringReport(serviceId),
        queryFn: () => offeringReportApi.get(serviceId),
        enabled: serviceId !== '',
    });
}
