import { useMutation, useQueryClient } from '@tanstack/react-query';
import { authApi } from '@/services/api/auth';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * Ends the session. The local state is cleared even if the server call fails,
 * because the user has asked to leave and the token expires on its own.
 */
export function useSignOut() {
    const signOut = useAuthStore((state) => state.signOut);
    const clearChurch = useChurchStore((state) => state.clearChurch);
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: authApi.logout,
        onSettled: () => {
            signOut();
            clearChurch();
            queryClient.clear();
        },
    });
}
