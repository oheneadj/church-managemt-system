import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Link } from 'react-router';
import { z } from 'zod';
import { AuthCard } from '@/components/auth/AuthCard';
import { ChoosePasswordForm } from '@/components/auth/ChoosePasswordForm';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { OTP_LENGTH } from '@/constants/onboarding';
import { ROUTES } from '@/constants/routes';
import { usePasswordReset } from '@/hooks/usePasswordReset';
import { plainErrorMessage } from '@/lib/plainErrorMessage';
import { showFieldErrors } from '@/lib/showFieldErrors';

const emailSchema = z.object({
    email: z.string().trim().toLowerCase().max(255).pipe(z.email('Enter a valid email address')),
});

type EmailValues = z.infer<typeof emailSchema>;

/** The steps of choosing a new password, in order. */
type Step = 'email' | 'reset' | 'done';

/**
 * Forgot password (D-054): ask for a code by email, then choose a new password with it. The answer to step 1 is the
 * same for every email, so this page never says whether an account exists. Choosing a password does not sign anyone in.
 */
export function ForgotPasswordPage() {
    const { requestCode } = usePasswordReset();
    const [step, setStep] = useState<Step>('email');
    const [email, setEmail] = useState('');

    const emailForm = useForm<EmailValues>({ resolver: zodResolver(emailSchema), defaultValues: { email: '' } });

    const sendCode = emailForm.handleSubmit((values) => {
        requestCode.mutate(values.email, {
            onSuccess: () => {
                setEmail(values.email);
                setStep('reset');
            },
            onError: (error) => showFieldErrors(error, emailForm.setError, ['email']),
        });
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
                <ChoosePasswordForm
                    email={email}
                    submitLabel="Save new password"
                    onDone={() => setStep('done')}
                    extra={
                        <Button type="button" variant="ghost" onClick={() => setStep('email')}>
                            Use a different email
                        </Button>
                    }
                />
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
