import { api } from '@/services';
import type { CodeSent, ResetPasswordInput, Session } from '@/types/api';

export const authApi = {
    /** Step 1 of staff sign-in: a right email and password make the server email a code. */
    login: (email: string, password: string): Promise<CodeSent> =>
        api.post<CodeSent>('/auth/login', { email, password }).then((r) => r.data),

    /** Step 2: the emailed code opens the session (a cookie in the browser, so there is no token here). */
    verifyLogin: (email: string, code: string): Promise<Session> =>
        api.post<Session>('/auth/login/verify', { email, code }).then((r) => r.data),

    /** Emails a reset code. The answer is the same for every email, so nothing about who has an account leaks. */
    forgotPassword: (email: string): Promise<CodeSent> => api.post<CodeSent>('/auth/password/forgot', { email }).then((r) => r.data),

    /** Sets a new password with the emailed code. It does not sign anyone in. */
    resetPassword: (input: ResetPasswordInput): Promise<void> => api.post('/auth/password/reset', input).then(() => undefined),

    logout: (): Promise<void> => api.post('/auth/logout').then(() => undefined),

    /** The current session, or a rejection with status 401 when nobody is signed in. */
    me: (): Promise<Session> =>
        api
            .get<{ data: Session['user']; church: Session['church'] }>('/auth/me')
            .then((r) => ({ user: r.data.data, church: r.data.church })),
};
