import { api } from '@/services';
import type { AuthSession, OtpRequested, User } from '@/types/api';

export const authApi = {
    requestOtp: (phone: string): Promise<OtpRequested> => api.post<OtpRequested>('/auth/otp/request', { phone }).then((r) => r.data),

    verifyOtp: (phone: string, otp: string): Promise<AuthSession> =>
        api.post<AuthSession>('/auth/otp/verify', { phone, otp }).then((r) => r.data),

    logout: (): Promise<void> => api.post('/auth/logout').then(() => undefined),

    me: (): Promise<User> => api.get<{ data: User }>('/auth/me').then((r) => r.data.data),
};
