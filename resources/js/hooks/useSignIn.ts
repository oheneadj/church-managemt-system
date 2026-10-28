import { useMutation } from '@tanstack/react-query';
import { authApi } from '@/services/api/auth';
import { useAuthStore } from '@/stores/useAuthStore';
import { useChurchStore } from '@/stores/useChurchStore';

/**
 * The two-step sign-in: ask for a code, then exchange it for a session.
 * A successful verify stores the user and the church context; the session cookie is set by the server.
 */
export function useSignIn() {
    const signIn = useAuthStore((state) => state.signIn);
    const setChurch = useChurchStore((state) => state.setChurch);

    const requestCode = useMutation({ mutationFn: authApi.requestOtp });

    const verifyCode = useMutation({
        mutationFn: ({ phone, otp }: { phone: string; otp: string }) => authApi.verifyOtp(phone, otp),
        onSuccess: (session) => {
            signIn(session.user);
            setChurch(session.church);
        },
    });

    return { requestCode, verifyCode };
}
