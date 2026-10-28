import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm, type FieldValues, type Path, type UseFormSetError } from 'react-hook-form';
import { Navigate, useNavigate } from 'react-router';
import { z } from 'zod';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ROUTES } from '@/constants/routes';
import { useSignIn } from '@/hooks/useSignIn';
import { ApiError } from '@/services/ApiError';
import { useAuthStore } from '@/stores/useAuthStore';

const phoneSchema = z.object({
    phone: z
        .string()
        .trim()
        .regex(/^\+?[\d\s-]{9,16}$/, 'Enter a valid phone number'),
});

const codeSchema = z.object({
    otp: z
        .string()
        .trim()
        .regex(/^\d{6}$/, 'Enter the 6-digit code'),
});

type PhoneValues = z.infer<typeof phoneSchema>;
type CodeValues = z.infer<typeof codeSchema>;

/**
 * Passwordless staff sign-in: phone number first, then the 6-digit code sent by SMS.
 * The phone number stays in component state only; it is never put in the URL.
 */
export function LoginPage() {
    const user = useAuthStore((state) => state.user);
    const navigate = useNavigate();
    const { requestCode, verifyCode } = useSignIn();
    const [phone, setPhone] = useState<string | null>(null);

    const phoneForm = useForm<PhoneValues>({ resolver: zodResolver(phoneSchema), defaultValues: { phone: '' } });
    const codeForm = useForm<CodeValues>({ resolver: zodResolver(codeSchema), defaultValues: { otp: '' } });

    if (user) {
        return <Navigate to={ROUTES.DASHBOARD} replace />;
    }

    const sendCode = phoneForm.handleSubmit((values) => {
        requestCode.mutate(values.phone, {
            onSuccess: () => setPhone(values.phone),
            onError: (error) => showFieldErrors(error, phoneForm.setError, ['phone']),
        });
    });

    const checkCode = codeForm.handleSubmit((values) => {
        if (phone === null) {
            return;
        }

        verifyCode.mutate(
            { phone, otp: values.otp },
            {
                onSuccess: () => void navigate(ROUTES.DASHBOARD, { replace: true }),
                onError: (error) => showFieldErrors(error, codeForm.setError, ['otp']),
            },
        );
    });

    const failure = (phone === null ? requestCode.error : verifyCode.error) as ApiError | null;

    return (
        <main className="flex min-h-svh items-center justify-center p-4">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>Sign in</CardTitle>
                    <CardDescription>
                        {phone === null ? 'Enter your phone number and we will text you a code.' : 'Enter the 6-digit code we sent you.'}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {phone === null ? (
                        <form onSubmit={sendCode} noValidate className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="phone">Phone number</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    placeholder="024 123 4567"
                                    aria-required="true"
                                    aria-invalid={phoneForm.formState.errors.phone ? true : undefined}
                                    aria-describedby="phone-error"
                                    {...phoneForm.register('phone')}
                                />
                                <FieldError id="phone-error" message={phoneForm.formState.errors.phone?.message} />
                            </div>
                            <ServerError error={failure} />
                            <Button type="submit" disabled={requestCode.isPending}>
                                {requestCode.isPending ? 'Sending…' : 'Send code'}
                            </Button>
                        </form>
                    ) : (
                        <form onSubmit={checkCode} noValidate className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="otp">Code</Label>
                                <Input
                                    id="otp"
                                    type="text"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    aria-required="true"
                                    aria-invalid={codeForm.formState.errors.otp ? true : undefined}
                                    aria-describedby="otp-error"
                                    {...codeForm.register('otp')}
                                />
                                <FieldError id="otp-error" message={codeForm.formState.errors.otp?.message} />
                            </div>
                            <ServerError error={failure} />
                            <Button type="submit" disabled={verifyCode.isPending}>
                                {verifyCode.isPending ? 'Checking…' : 'Sign in'}
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setPhone(null)}>
                                Use a different number
                            </Button>
                        </form>
                    )}
                </CardContent>
            </Card>
        </main>
    );
}

function FieldError({ id, message }: { id: string; message: string | undefined }) {
    return (
        <p id={id} role="alert" className="min-h-4 text-sm text-destructive">
            {message}
        </p>
    );
}

function ServerError({ error }: { error: ApiError | null }) {
    if (error === null || Object.keys(error.fieldErrors).length > 0) {
        return null;
    }

    return (
        <p role="alert" className="text-sm text-destructive">
            {error.message}
        </p>
    );
}

/**
 * Puts the server's validation messages next to the form fields they belong to.
 */
function showFieldErrors<T extends FieldValues>(error: Error, setError: UseFormSetError<T>, fields: readonly Path<T>[]): void {
    if (!(error instanceof ApiError)) {
        return;
    }

    for (const field of fields) {
        const message = error.fieldErrors[field]?.[0];

        if (message !== undefined) {
            setError(field, { message });
        }
    }
}
