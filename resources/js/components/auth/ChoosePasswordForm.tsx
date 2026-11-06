import { zodResolver } from '@hookform/resolvers/zod';
import type { ReactNode } from 'react';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { OTP_LENGTH } from '@/constants/onboarding';
import { PASSWORD_MIN_LENGTH } from '@/constants/password';
import { usePasswordReset } from '@/hooks/usePasswordReset';
import { newPasswordField } from '@/lib/newPasswordField';
import { plainErrorMessage } from '@/lib/plainErrorMessage';
import { showFieldErrors } from '@/lib/showFieldErrors';

const choosePasswordSchema = z
    .object({
        email: z.string().trim().toLowerCase().max(255).pipe(z.email('Enter a valid email address')),
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

type ChoosePasswordValues = z.infer<typeof choosePasswordSchema>;

const FIELDS = ['email', 'password'] as const;

interface ChoosePasswordFormProps {
    /** The email the code was sent to, when the person has already given it. Leave out to ask for it. */
    email?: string;
    /** What the button says, for example "Save new password". */
    submitLabel: string;
    /** Called once the password is saved. */
    onDone: () => void;
    /** Another button under the form, for example "Use a different email". */
    extra?: ReactNode;
}

/**
 * The form that turns an emailed code into a password: the code, the new password typed twice, and the email when it is not
 * already known (D-054). It is used after "Forgot your password?" (reset code) and for an invitation (the same kind of code,
 * D-055), because the server treats both the same. Choosing a password does not sign anyone in.
 */
export function ChoosePasswordForm({ email, submitLabel, onDone, extra }: ChoosePasswordFormProps) {
    const { resetPassword } = usePasswordReset();
    const form = useForm<ChoosePasswordValues>({
        resolver: zodResolver(choosePasswordSchema),
        defaultValues: { email: email ?? '', code: '', password: '', password_confirmation: '' },
    });
    const { errors } = form.formState;

    const save = form.handleSubmit((values) => {
        resetPassword.mutate(values, {
            onSuccess: onDone,
            onError: (error) => {
                const shown = showFieldErrors(error, form.setError, FIELDS);

                // "Invalid code" has no field of its own in the response, so it belongs to the code box.
                if (shown.length === 0 && plainErrorMessage(error) !== null) {
                    form.setError('code', { message: error.message });
                }
            },
        });
    });

    return (
        <form onSubmit={save} noValidate className="flex flex-col gap-4">
            {email === undefined && (
                <TextField
                    id="email"
                    label="Email address"
                    type="email"
                    inputMode="email"
                    autoComplete="username"
                    required
                    error={errors.email?.message}
                    {...form.register('email')}
                />
            )}
            <TextField
                id="code"
                label="Code"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={OTP_LENGTH}
                required
                error={errors.code?.message}
                {...form.register('code')}
            />
            <TextField
                id="password"
                label="New password"
                type="password"
                autoComplete="new-password"
                required
                error={errors.password?.message}
                {...form.register('password')}
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
                error={errors.password_confirmation?.message}
                {...form.register('password_confirmation')}
            />
            <Button type="submit" disabled={resetPassword.isPending}>
                {resetPassword.isPending ? 'Saving…' : submitLabel}
            </Button>
            {extra}
        </form>
    );
}
