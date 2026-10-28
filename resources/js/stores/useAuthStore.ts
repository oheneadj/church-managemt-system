import { create } from 'zustand';
import type { User } from '@/types/api';

interface AuthState {
    /** The signed-in user. The session itself is an httpOnly cookie the browser holds; JavaScript never sees it. */
    user: User | null;
    signIn: (user: User) => void;
    signOut: () => void;
}

export const useAuthStore = create<AuthState>()((set) => ({
    user: null,
    signIn: (user) => set({ user }),
    signOut: () => set({ user: null }),
}));
