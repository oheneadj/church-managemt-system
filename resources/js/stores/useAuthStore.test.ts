import { beforeEach, describe, expect, it } from 'vitest';
import { useAuthStore } from '@/stores/useAuthStore';
import type { User } from '@/types/api';

const user: User = { id: '01', name: 'Akosua', phone: '+233244123456', email: null, roles: [], permissions: [] };

describe('useAuthStore', () => {
    beforeEach(() => useAuthStore.setState({ user: null }));

    it('starts signed out', () => {
        expect(useAuthStore.getState().user).toBeNull();
    });

    it('holds the user after sign in and clears it on sign out', () => {
        useAuthStore.getState().signIn(user);
        expect(useAuthStore.getState().user).toEqual(user);

        useAuthStore.getState().signOut();
        expect(useAuthStore.getState().user).toBeNull();
    });

    it('never writes anything to browser storage', () => {
        useAuthStore.getState().signIn(user);

        expect(localStorage.length).toBe(0);
        expect(sessionStorage.length).toBe(0);
    });
});
