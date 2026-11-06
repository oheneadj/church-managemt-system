import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link, Navigate, useNavigate } from 'react-router';
import { z } from 'zod';
import { AuthCard } from '@/components/auth/AuthCard';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { OTP_LENGTH } from '@/constants/onboarding';
import { ROUTES } from '@/constants/routes';
import { useSignIn } from '@/hooks/useSignIn';
import { plainErrorMessage } from '@/lib/plainErrorMessage';
import { showFieldErrors } from '@/lib/showFieldErrors';
import { useAuthStore } from '@/stores/useAuthStore';

const credentialsSchema = z.object({
    email: z.string().trim().toLowerCase().max(255).pipe(z.email('Enter a valid email address')),
    password: z.string().min(1, 'Enter your password'),
});

const codeSchema = z.object({
    code: z
        .string()
        .trim()
        .regex(new RegExp(`^\\d{${OTP_LENGTH}}$`), `Enter the ${OTP_LENGTH}-digit code`),
});

type CredentialsValues = z.infer<typeof credentialsSchema>;
type CodeValues = z.infer<typeof codeSchema>;

/**
 * Staff sign-in (D-054): email and password first, then the 6-digit code emailed to them.
 * The email stays in component state only; it is never put in the URL, and the password is not kept after step 1.
 */
export function LoginPage() {
    const user = useAuthStore((state) => state.user);
    const navigate = useNavigate();
    const { checkPassword, verifyCode } = useSignIn();
    const [email, setEmail] = useState<string | null>(null);

    const credentialsForm = useForm<CredentialsValues>({
        resolver: zodResolver(credentialsSchema),
        defaultValues: { email: '', password: '' },
    });
    const codeForm = useForm<CodeValues>({ resolver: zodResolver(codeSchema), defaultValues: { code: '' } });

    if (user) {
        return <Navigate to={ROUTES.DASHBOARD} replace />;
    }

    const sendCode = credentialsForm.handleSubmit((values) => {
        checkPassword.mutate(values, {
            onSuccess: () => {
                setEmail(values.email);
                credentialsForm.resetField('password');
            },
            onError: (error) => showFieldErrors(error, credentialsForm.setError, ['email', 'password']),
        });
    });

    const checkCode = codeForm.handleSubmit((values) => {
        if (email === null) {
            return;
        }

        verifyCode.mutate(
            { email, code: values.code },
            {
                onSuccess: () => void navigate(ROUTES.DASHBOARD, { replace: true }),
                onError: (error) => showFieldErrors(error, codeForm.setError, ['code']),
            },
        );
    });

    const changeAccount = () => {
        verifyCode.reset();
        codeForm.reset();
        setEmail(null);
    };

    const failure = email === null ? checkPassword.error : verifyCode.error;
    const failureMessage = plainErrorMessage(failure);

    return (
        <AuthCard
            title="Sign in"
            description={
                email === null ? 'Enter your email and password.' : `We emailed a ${OTP_LENGTH}-digit code to ${email}. Enter it below.`
            }
        >
            {email === null ? (
                <form onSubmit={sendCode} noValidate className="flex flex-col gap-4">
                    <TextField
                        id="email"
                        label="Email address"
                        type="email"
                        inputMode="email"
                        autoComplete="username"
                        required
                        error={credentialsForm.formState.errors.email?.message}
                        {...credentialsForm.register('email')}
                    />
                    <TextField
                        id="password"
                        label="Password"
                        type="password"
                        autoComplete="current-password"
                        required
                        error={credentialsForm.formState.errors.password?.message}
                        {...credentialsForm.register('password')}
                    />
                    {failureMessage !== null && <ErrorNotice message={failureMessage} />}
                    <Button type="submit" disabled={checkPassword.isPending}>
                        {checkPassword.isPending ? 'Checking…' : 'Continue'}
                    </Button>
                    <Button asChild variant="link">
                        <Link to={ROUTES.FORGOT_PASSWORD}>Forgot your password?</Link>
                    </Button>
                    <Button asChild variant="link">
                        <Link to={ROUTES.CHOOSE_PASSWORD}>Have an invitation code?</Link>
                    </Button>
                </form>
            ) : (
                <form onSubmit={checkCode} noValidate className="flex flex-col gap-4">
                    <TextField
                        id="code"
                        label="Code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={OTP_LENGTH}
                        required
                        error={codeForm.formState.errors.code?.message}
                        {...codeForm.register('code')}
                    />
                    {failureMessage !== null && <ErrorNotice message={failureMessage} />}
                    <Button type="submit" disabled={verifyCode.isPending}>
                        {verifyCode.isPending ? 'Checking…' : 'Sign in'}
                    </Button>
                    <Button type="button" variant="ghost" onClick={changeAccount}>
                        Use a different account
                    </Button>
                </form>
            )}
        </AuthCard>
    );
}
