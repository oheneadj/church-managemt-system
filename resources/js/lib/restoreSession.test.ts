import { beforeEach, describe, expect, it, vi } from 'vitest';
import { restoreSession } from '@/lib/restoreSession';
import { authApi } from '@/services/api/auth';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';
import type { Session } from '@/types/api';

vi.mock('@/services/api/auth');

const session: Session = {
    user: { id: '01', name: 'Akosua', phone: '+233244123456', email: null, roles: [], permissions: [] },
    church: { id: '02', name: 'Grace Assembly', slug: 'grace', plan: 'starter', sms_credits: 0 },
};

describe('restoreSession', () => {
    beforeEach(() => {
        useAuthStore.setState({ user: null });
        useChurchStore.setState({ church: null });
    });

    it('signs the user back in when the browser still has a valid session', async () => {
        vi.mocked(authApi.me).mockResolvedValue(session);

        await restoreSession();

        expect(useAuthStore.getState().user?.name).toBe('Akosua');
        expect(useChurchStore.getState().church?.name).toBe('Grace Assembly');
    });

    it('leaves the stores empty when nobody is signed in, without throwing', async () => {
        vi.mocked(authApi.me).mockRejectedValue(new ApiError('Unauthenticated.', 401));

        await expect(restoreSession()).resolves.toBeUndefined();

        expect(useAuthStore.getState().user).toBeNull();
        expect(useChurchStore.getState().church).toBeNull();
    });
});
