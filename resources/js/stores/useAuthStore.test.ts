import { beforeEach, describe, expect, it } from 'vitest';
import { useAuthStore } from '@/stores/useAuthStore';
import type { User } from '@/types/api';

const user: User = { id: '01', name: 'Akosua', phone: '+233244123456', email: null, roles: [], permissions: [] };

describe('useAuthStore', () => {
    beforeEach(() => useAuthStore.setState({ token: null, user: null }));

    it('starts signed out', () => {
        expect(useAuthStore.getState().token).toBeNull();
    });

    it('keeps the token in memory after sign in and clears it on sign out', () => {
        useAuthStore.getState().signIn('1|abc', user);
        expect(useAuthStore.getState()).toMatchObject({ token: '1|abc', user });

        useAuthStore.getState().signOut();
        expect(useAuthStore.getState()).toMatchObject({ token: null, user: null });
    });

    it('never writes the token to browser storage', () => {
        useAuthStore.getState().signIn('1|secret', user);

        expect(JSON.stringify({ ...localStorage })).not.toContain('secret');
        expect(JSON.stringify({ ...sessionStorage })).not.toContain('secret');
    });
});
