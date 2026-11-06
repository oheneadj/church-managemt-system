import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router';
import { z } from 'zod';
import { AuthCard } from '@/components/auth/AuthCard';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { OTP_LENGTH } from '@/constants/onboarding';
import { PASSWORD_MIN_LENGTH } from '@/constants/password';
import { ROUTES } from '@/constants/routes';
import { usePasswordReset } from '@/hooks/usePasswordReset';
import { newPasswordField } from '@/lib/newPasswordField';
import { plainErrorMessage } from '@/lib/plainErrorMessage';
import { showFieldErrors } from '@/lib/showFieldErrors';

const emailSchema = z.object({
    email: z.string().trim().toLowerCase().max(255).pipe(z.email('Enter a valid email address')),
});

const resetSchema = z
    .object({
        code: z
            .string()
            .trim()
            .regex(new RegExp(`^\\d{${OTP_LENGTH}}$`), `Enter the ${OTP_LENGTH}-digit code`),
        password: newPasswordField,
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        error: 'The two passwords do not match',
    });

type EmailValues = z.infer<typeof emailSchema>;
type ResetValues = z.infer<typeof resetSchema>;

/** The steps of choosing a new password, in order. */
type Step = 'email' | 'reset' | 'done';

/**
 * Forgot password (D-054): ask for a code by email, then choose a new password with it. The answer to step 1 is the
 * same for every email, so this page never says whether an account exists. Choosing a password does not sign anyone in.
 */
export function ForgotPasswordPage() {
    const { requestCode, resetPassword } = usePasswordReset();
    const [step, setStep] = useState<Step>('email');
    const [email, setEmail] = useState('');

    const emailForm = useForm<EmailValues>({ resolver: zodResolver(emailSchema), defaultValues: { email: '' } });
    const resetForm = useForm<ResetValues>({
        resolver: zodResolver(resetSchema),
        defaultValues: { code: '', password: '', password_confirmation: '' },
    });

    const sendCode = emailForm.handleSubmit((values) => {
        requestCode.mutate(values.email, {
            onSuccess: () => {
                setEmail(values.email);
                setStep('reset');
            },
            onError: (error) => showFieldErrors(error, emailForm.setError, ['email']),
        });
    });

    const choosePassword = resetForm.handleSubmit((values) => {
        resetPassword.mutate(
            { email, ...values },
            {
                onSuccess: () => setStep('done'),
                onError: (error) => {
                    const shown = showFieldErrors(error, resetForm.setError, ['password']);

                    // "Invalid code" has no field of its own in the response, so it belongs to the code box.
                    if (shown.length === 0 && plainErrorMessage(error) !== null) {
                        resetForm.setError('code', { message: error.message });
                    }
                },
            },
        );
    });

    if (step === 'done') {
        return (
            <AuthCard title="Password changed" description="You can now sign in with your new password.">
                <Button asChild className="w-full">
                    <Link to={ROUTES.LOGIN}>Go to sign in</Link>
                </Button>
            </AuthCard>
        );
    }

    if (step === 'reset') {
        return (
            <AuthCard
                title="Choose a new password"
                description={`If ${email} belongs to a staff member, we emailed a ${OTP_LENGTH}-digit code to it.`}
            >
                <form onSubmit={choosePassword} noValidate className="flex flex-col gap-4">
                    <TextField
                        id="code"
                        label="Code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={OTP_LENGTH}
                        required
                        error={resetForm.formState.errors.code?.message}
                        {...resetForm.register('code')}
                    />
                    <TextField
                        id="password"
                        label="New password"
                        type="password"
                        autoComplete="new-password"
                        required
                        error={resetForm.formState.errors.password?.message}
                        {...resetForm.register('password')}
                    />
                    <p className="-mt-2 text-sm text-muted-foreground">
                        At least {PASSWORD_MIN_LENGTH} characters. A few ordinary words together make a strong one.
                    </p>
                    <TextField
                        id="password_confirmation"
                        label="Type the new password again"
                        type="password"
                        autoComplete="new-password"
                        required
                        error={resetForm.formState.errors.password_confirmation?.message}
                        {...resetForm.register('password_confirmation')}
                    />
                    <Button type="submit" disabled={resetPassword.isPending}>
                        {resetPassword.isPending ? 'Saving…' : 'Save new password'}
                    </Button>
                    <Button type="button" variant="ghost" onClick={() => setStep('email')}>
                        Use a different email
                    </Button>
                </form>
            </AuthCard>
        );
    }

    const failureMessage = plainErrorMessage(requestCode.error);

    return (
        <AuthCard title="Forgot your password?" description="Enter your email and we will send you a code to choose a new one.">
            <form onSubmit={sendCode} noValidate className="flex flex-col gap-4">
                <TextField
                    id="email"
                    label="Email address"
                    type="email"
                    inputMode="email"
                    autoComplete="username"
                    required
                    error={emailForm.formState.errors.email?.message}
                    {...emailForm.register('email')}
                />
                {failureMessage !== null && <ErrorNotice message={failureMessage} />}
                <Button type="submit" disabled={requestCode.isPending}>
                    {requestCode.isPending ? 'Sending…' : 'Email me a code'}
                </Button>
                <Button asChild variant="link">
                    <Link to={ROUTES.LOGIN}>Back to sign in</Link>
                </Button>
            </form>
        </AuthCard>
    );
}
