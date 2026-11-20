import { useMutation } from '@tanstack/react-query';
import { authApi } from '@/services/api/auth';
import type { ResetPasswordInput } from '@/types/api';

/**
 * The two calls of choosing a new password: ask for an emailed code, then set the password with it.
 */
export function usePasswordReset() {
    const requestCode = useMutation({ mutationFn: (email: string) => authApi.forgotPassword(email) });
    const resetPassword = useMutation({ mutationFn: (input: ResetPasswordInput) => authApi.resetPassword(input) });

    return { requestCode, resetPassword };
}
