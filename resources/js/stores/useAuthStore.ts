import { create } from 'zustand';
import type { User } from '@/types/api';

interface AuthState {
    /** Kept in memory only: bearer tokens must never reach localStorage (addendum React §11). */
    token: string | null;
    user: User | null;
    signIn: (token: string, user: User) => void;
    signOut: () => void;
}

export const useAuthStore = create<AuthState>()((set) => ({
    token: null,
    user: null,
    signIn: (token, user) => set({ token, user }),
    signOut: () => set({ token: null, user: null }),
}));
