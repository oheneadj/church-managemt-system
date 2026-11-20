import { api } from '@/services';
import type { CodeSent } from '@/types/api';
import type { Page } from '@/types/members';
import type { PlatformAdmin, SmsPackage, SmsPackageInput } from '@/types/platform';

export const platformApi = {
    /** Step 1 of platform sign-in: a right email and password make the server email a code. */
    login: (email: string, password: string): Promise<CodeSent> =>
        api.post<CodeSent>('/platform/auth/login', { email, password }).then((r) => r.data),

    /** Step 2: the emailed code opens the session (a cookie, so there is no token here). */
    verifyLogin: (email: string, code: string): Promise<PlatformAdmin> =>
        api.post<{ data: PlatformAdmin }>('/platform/auth/login/verify', { email, code }).then((r) => r.data.data),

    /** The signed-in admin, or a rejection with status 401 when nobody is signed in. */
    me: (): Promise<PlatformAdmin> => api.get<{ data: PlatformAdmin }>('/platform/auth/me').then((r) => r.data.data),

    logout: (): Promise<void> => api.post('/platform/auth/logout').then(() => undefined),

    /** Every SMS credit package, switched off ones too, in the order churches see them. */
    packages: (): Promise<SmsPackage[]> =>
        api.get<Page<SmsPackage>>('/platform/sms-packages', { params: { per_page: 100 } }).then((r) => r.data.data),

    createPackage: (input: SmsPackageInput): Promise<SmsPackage> =>
        api.post<{ data: SmsPackage }>('/platform/sms-packages', input).then((r) => r.data.data),

    /** Changes the fields sent. There is no delete: a package no longer sold is switched off. */
    updatePackage: (id: string, input: Partial<SmsPackageInput> & { is_active?: boolean }): Promise<SmsPackage> =>
        api.put<{ data: SmsPackage }>(`/platform/sms-packages/${id}`, input).then((r) => r.data.data),
};
