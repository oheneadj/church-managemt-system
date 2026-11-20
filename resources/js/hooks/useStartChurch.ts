import { useMutation } from '@tanstack/react-query';
import { onboardingApi } from '@/services/api/onboarding';
import type { StartChurchInput } from '@/types/onboarding';

/**
 * The two calls of starting a church: ask for a code, then create the church with it.
 */
export function useStartChurch() {
    const requestCode = useMutation({ mutationFn: (adminPhone: string) => onboardingApi.requestOtp(adminPhone) });
    const startChurch = useMutation({ mutationFn: (input: StartChurchInput) => onboardingApi.startChurch(input) });

    return { requestCode, startChurch };
}
