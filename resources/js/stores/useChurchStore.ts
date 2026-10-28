import { create } from 'zustand';
import type { Church } from '@/types/api';

interface ChurchState {
    church: Church | null;
    setChurch: (church: Church) => void;
    clearChurch: () => void;
}

export const useChurchStore = create<ChurchState>()((set) => ({
    church: null,
    setChurch: (church) => set({ church }),
    clearChurch: () => set({ church: null }),
}));
