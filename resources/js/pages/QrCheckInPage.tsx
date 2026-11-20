import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useParams } from 'react-router';
import { z } from 'zod';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { OTP_LENGTH } from '@/constants/onboarding';
import { PHONE_INPUT_PATTERN } from '@/constants/validation';
import { useQrCheckIn } from '@/hooks/useQrCheckIn';
import { showFieldErrors } from '@/lib/showFieldErrors';
import { ApiError } from '@/services/ApiError';
import type { QrCheckedIn } from '@/types/services';

const phoneSchema = z.object({ phone: z.string().trim().regex(PHONE_INPUT_PATTERN, 'Enter a valid phone number') });
const codeSchema = z.object({
    otp: z
        .string()
        .trim()
        .regex(new RegExp(`^\\d{${OTP_LENGTH}}$`), `Enter the ${OTP_LENGTH}-digit code`),
});

/**
 * The page a member sees after scanning the QR code at the venue. No sign-in: they enter their phone number, get a code by
 * text, and are checked in. If the code on the screen has expired they are told to scan again.
 */
export function QrCheckInPage() {
    const { token = '' } = useParams();
    const { service, requestCode, confirm } = useQrCheckIn(token);
    const [phone, setPhone] = useState<string | null>(null);
    const [done, setDone] = useState<QrCheckedIn | null>(null);
    const phoneForm = useForm({ resolver: zodResolver(phoneSchema), defaultValues: { phone: '' } });
    const codeForm = useForm({ resolver: zodResolver(codeSchema), defaultValues: { otp: '' } });

    if (service.isPending) {
        return <Skeleton aria-busy="true" className="mx-auto mt-10 h-48 max-w-sm rounded-xl" />;
    }

    if (service.isError) {
        const expired = service.error instanceof ApiError && service.error.status === 404;

        return (
            <Page title="Check in">
                <ErrorNotice message={expired ? 'This code has expired. Please scan the screen again.' : service.error.message} />
            </Page>
        );
    }

    if (!service.data.is_open) {
        return (
            <Page title={service.data.name}>
                <p>Check-in is closed for this service.</p>
            </Page>
        );
    }

    if (done !== null) {
        return (
            <Page title={`Welcome, ${done.first_name}!`}>
                <p>
                    {done.already_checked_in
                        ? "You're already checked in. See you inside."
                        : `You're checked in to ${done.service_name}. God bless you.`}
                </p>
            </Page>
        );
    }

    const sendCode = phoneForm.handleSubmit((values) => {
        requestCode.mutate(values.phone, {
            onSuccess: () => setPhone(values.phone),
            onError: (error) => showFieldErrors(error, phoneForm.setError, ['phone']),
        });
    });

    const checkIn = codeForm.handleSubmit((values) => {
        if (phone === null) return;

        confirm.mutate(
            { phone, otp: values.otp },
            {
                onSuccess: setDone,
                onError: (error) => {
                    if (showFieldErrors(error, codeForm.setError, ['otp']).length === 0) {
                        codeForm.setError('otp', { message: error.message });
                    }
                },
            },
        );
    });

    return (
        <Page
            title={`Check in to ${service.data.name}`}
            description={phone === null ? 'Enter your phone number and we will text you a code.' : 'Enter the code we sent you.'}
        >
            {phone === null ? (
                <form onSubmit={sendCode} noValidate className="flex flex-col gap-3">
                    <TextField
                        id="phone"
                        label="Phone number"
                        type="tel"
                        inputMode="tel"
                        autoComplete="tel"
                        required
                        placeholder="024 123 4567"
                        error={phoneForm.formState.errors.phone?.message}
                        {...phoneForm.register('phone')}
                    />
                    {requestCode.isError && !phoneForm.formState.errors.phone && <ErrorNotice message={requestCode.error.message} />}
                    <Button type="submit" size="lg" disabled={requestCode.isPending}>
                        {requestCode.isPending ? 'Sending…' : 'Text me a code'}
                    </Button>
                </form>
            ) : (
                <form onSubmit={checkIn} noValidate className="flex flex-col gap-3">
                    <TextField
                        id="otp"
                        label="Code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        maxLength={OTP_LENGTH}
                        required
                        placeholder="123456"
                        error={codeForm.formState.errors.otp?.message}
                        {...codeForm.register('otp')}
                    />
                    <Button type="submit" size="lg" disabled={confirm.isPending}>
                        {confirm.isPending ? 'Checking…' : 'Check me in'}
                    </Button>
                    <Button type="button" variant="ghost" onClick={() => setPhone(null)}>
                        Use a different number
                    </Button>
                </form>
            )}
        </Page>
    );
}

interface PageProps {
    title: string;
    description?: string;
    children: React.ReactNode;
}

/** The centred card every state of the check-in page sits in. */
function Page({ title, description, children }: PageProps) {
    return (
        <main className="flex min-h-svh items-center justify-center p-4">
            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle>
                        <h1>{title}</h1>
                    </CardTitle>
                    {description && <CardDescription>{description}</CardDescription>}
                </CardHeader>
                <CardContent>{children}</CardContent>
            </Card>
        </main>
    );
}
