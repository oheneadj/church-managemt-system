import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QUERY_KEYS } from '@/constants/queryKeys';
import { platformApi } from '@/services/api/platform';
import type { SmsPackageInput } from '@/types/platform';

/**
 * Who the platform admin is. The session is a cookie, so a page refresh asks the server; no answer but a 401 means signed
 * out, and it is never retried.
 */
export function usePlatformSession() {
    return useQuery({ queryKey: QUERY_KEYS.platform.me, queryFn: platformApi.me, retry: false, staleTime: Infinity });
}

/** The two-step platform sign-in: email and password make the server email a code, then the code opens the session. */
export function usePlatformSignIn() {
    const queryClient = useQueryClient();

    return {
        checkPassword: useMutation({
            mutationFn: ({ email, password }: { email: string; password: string }) => platformApi.login(email, password),
        }),
        verifyCode: useMutation({
            mutationFn: ({ email, code }: { email: string; code: string }) => platformApi.verifyLogin(email, code),
            onSuccess: (admin) => queryClient.setQueryData(QUERY_KEYS.platform.me, admin),
        }),
    };
}

/** Signing out forgets everything held for the platform side. */
export function usePlatformSignOut() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => platformApi.logout(),
        onSuccess: () => queryClient.removeQueries({ queryKey: QUERY_KEYS.platform.me }),
    });
}

/** Every SMS credit package, switched off ones too. */
export function useSmsPackages() {
    return useQuery({ queryKey: QUERY_KEYS.platform.packages, queryFn: () => platformApi.packages() });
}

/** Adding, changing and switching packages on or off. Each refreshes the package list. */
export function useSmsPackageMutations() {
    const queryClient = useQueryClient();
    const refresh = () => queryClient.invalidateQueries({ queryKey: QUERY_KEYS.platform.packages });

    return {
        create: useMutation({ mutationFn: (input: SmsPackageInput) => platformApi.createPackage(input), onSuccess: refresh }),
        update: useMutation({
            mutationFn: ({ id, input }: { id: string; input: Partial<SmsPackageInput> }) => platformApi.updatePackage(id, input),
            onSuccess: refresh,
        }),
        setActive: useMutation({
            mutationFn: ({ id, isActive }: { id: string; isActive: boolean }) => platformApi.updatePackage(id, { is_active: isActive }),
            onSuccess: refresh,
        }),
    };
}
