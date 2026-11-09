import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Navigate, useNavigate } from 'react-router';
import { z } from 'zod';
import { AuthCard } from '@/components/auth/AuthCard';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { OTP_LENGTH } from '@/constants/onboarding';
import { ROUTES } from '@/constants/routes';
import { usePlatformSession, usePlatformSignIn } from '@/hooks/usePlatform';
import { plainErrorMessage } from '@/lib/plainErrorMessage';
import { showFieldErrors } from '@/lib/showFieldErrors';

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
 * The platform owner's sign-in (D-069): email and password first, then the 6-digit code emailed to them, as church staff sign in
 * (D-054). The email stays in component state only, and the password is not kept after step 1.
 */
export function PlatformLoginPage() {
    const session = usePlatformSession();
    const navigate = useNavigate();
    const { checkPassword, verifyCode } = usePlatformSignIn();
    const [email, setEmail] = useState<string | null>(null);
    const credentialsForm = useForm<CredentialsValues>({
        resolver: zodResolver(credentialsSchema),
        defaultValues: { email: '', password: '' },
    });
    const codeForm = useForm<CodeValues>({ resolver: zodResolver(codeSchema), defaultValues: { code: '' } });

    if (session.isSuccess) {
        return <Navigate to={ROUTES.PLATFORM_HOME} replace />;
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
                onSuccess: () => void navigate(ROUTES.PLATFORM_HOME, { replace: true }),
                onError: (error) => showFieldErrors(error, codeForm.setError, ['code']),
            },
        );
    });

    const changeAccount = () => {
        verifyCode.reset();
        codeForm.reset();
        setEmail(null);
    };

    const failureMessage = plainErrorMessage(email === null ? checkPassword.error : verifyCode.error);

    return (
        <AuthCard
            title="Platform sign in"
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
