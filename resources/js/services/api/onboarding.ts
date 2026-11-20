import { api } from '@/services';
import type { OtpRequested } from '@/types/api';
import type { StartChurchInput, StartedChurch } from '@/types/onboarding';

export const onboardingApi = {
    /** Texts a sign-up code to the phone of the person starting a church. */
    requestOtp: (adminPhone: string): Promise<OtpRequested> =>
        api.post<OtpRequested>('/onboarding/otp', { admin_phone: adminPhone }).then((r) => r.data),

    /** Creates the church and its first admin, once the code proves the phone. */
    startChurch: (input: StartChurchInput): Promise<StartedChurch> =>
        api.post<StartedChurch>('/onboarding/churches', input).then((r) => r.data),
};
