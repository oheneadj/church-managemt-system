import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { churchApi } from '@/services/api/church';
import { useChurchStore } from '@/stores/useChurchStore';
import type { ChurchProfileInput, ChurchSettings } from '@/types/church';

/** The church's own details. */
export function useChurchProfile() {
    return useQuery({ queryKey: QUERY_KEYS.church.profile, queryFn: churchApi.profile });
}

/** The church's settings, with defaults filled in. */
export function useChurchSettings() {
    return useQuery({ queryKey: QUERY_KEYS.church.settings, queryFn: churchApi.settings });
}

/**
 * Saving the details and the settings. Each keeps the saved copy in the cache, and a new church name is put in the menu
 * at once, so the header does not show the old name until the next sign-in.
 */
export function useChurchSettingsMutations() {
    const queryClient = useQueryClient();

    return {
        updateProfile: useMutation({
            mutationFn: (input: ChurchProfileInput) => churchApi.updateProfile(input),
            onSuccess: (profile) => {
                queryClient.setQueryData(QUERY_KEYS.church.profile, profile);
                const church = useChurchStore.getState().church;

                if (church !== null) {
                    useChurchStore.getState().setChurch({ ...church, name: profile.name });
                }
            },
        }),

        updateSettings: useMutation({
            mutationFn: (settings: ChurchSettings) => churchApi.updateSettings(settings),
            onSuccess: (settings) => queryClient.setQueryData(QUERY_KEYS.church.settings, settings),
        }),
    };
}
