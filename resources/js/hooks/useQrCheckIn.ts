import { useMutation, useQuery } from '@tanstack/react-query';
import { checkinApi } from '@/services/api/checkin';

/**
 * The steps of a member's QR check-in: which service the scanned code is for, asking for a texted code, and confirming it.
 * An expired code is a final answer, so the service lookup does not retry.
 */
export function useQrCheckIn(token: string) {
    return {
        service: useQuery({ queryKey: ['checkin', 'service', token], queryFn: () => checkinApi.service(token), retry: false }),

        requestCode: useMutation({ mutationFn: (phone: string) => checkinApi.requestCode(token, phone) }),

        confirm: useMutation({ mutationFn: ({ phone, otp }: { phone: string; otp: string }) => checkinApi.confirm(token, phone, otp) }),
    };
}
