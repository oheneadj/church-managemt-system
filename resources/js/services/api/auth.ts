import { api } from '@/services';
import type { OtpRequested, Session } from '@/types/api';

export const authApi = {
    requestOtp: (phone: string): Promise<OtpRequested> => api.post<OtpRequested>('/auth/otp/request', { phone }).then((r) => r.data),

    verifyOtp: (phone: string, otp: string): Promise<Session> => api.post<Session>('/auth/otp/verify', { phone, otp }).then((r) => r.data),

    logout: (): Promise<void> => api.post('/auth/logout').then(() => undefined),

    /** The current session, or a rejection with status 401 when nobody is signed in. */
    me: (): Promise<Session> =>
        api
            .get<{ data: Session['user']; church: Session['church'] }>('/auth/me')
            .then((r) => ({ user: r.data.data, church: r.data.church })),
};
