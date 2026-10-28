import { authApi } from '@/services/api/auth';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * Asks the server whether the browser still holds a valid session and, if so, fills the stores.
 * This is what keeps someone signed in across a page refresh. "Not signed in" is a normal answer,
 * not an error, so a failure just leaves the stores empty.
 */
export async function restoreSession(): Promise<void> {
    try {
        const session = await authApi.me();

        useAuthStore.getState().signIn(session.user);
        useChurchStore.getState().setChurch(session.church);
    } catch {
        useAuthStore.getState().signOut();
        useChurchStore.getState().clearChurch();
    }
}
