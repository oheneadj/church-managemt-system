import { useMutation } from '@tanstack/react-query';
import { authApi } from '@/services/api/auth';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * The two-step staff sign-in (D-054): email and password make the server email a code, then the code opens the session.
 * A successful second step stores the user and the church context; the session cookie is set by the server.
 */
export function useSignIn() {
    const signIn = useAuthStore((state) => state.signIn);
    const setChurch = useChurchStore((state) => state.setChurch);

    const checkPassword = useMutation({
        mutationFn: ({ email, password }: { email: string; password: string }) => authApi.login(email, password),
    });

    const verifyCode = useMutation({
        mutationFn: ({ email, code }: { email: string; code: string }) => authApi.verifyLogin(email, code),
        onSuccess: (session) => {
            signIn(session.user);
            setChurch(session.church);
        },
    });

    return { checkPassword, verifyCode };
}
