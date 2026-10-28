import { zodResolver } from '@hookform/resolvers/zod';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { ChurchStarted } from '@/components/Onboarding/ChurchStarted';
import { blankStartChurchValues, startChurchSchema, toStartChurchInput } from '@/components/Onboarding/startChurchSchema';
import type { StartChurchValues } from '@/components/Onboarding/startChurchSchema';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { OTP_LENGTH, PREFIX_MAX_LENGTH } from '@/constants/onboarding';
import { useStartChurch } from '@/hooks/useStartChurch';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { StartedChurch } from '@/types/onboarding';

const FIELDS = ['church_name', 'slug', 'denomination', 'city', 'member_number_prefix', 'admin_name', 'admin_phone', 'otp'] as const;

/**
 * The "start a church" form. The person proves their phone with a texted code on the same page, then the
 * church and its first admin are created together. Server messages appear next to the box they belong to.
 */
export function StartChurchForm() {
    const { requestCode, startChurch } = useStartChurch();
    const [codeSent, setCodeSent] = useState(false);
    const [started, setStarted] = useState<StartedChurch | null>(null);
    const form = useForm<StartChurchValues>({ resolver: zodResolver(startChurchSchema), defaultValues: blankStartChurchValues });
    const { register, formState } = form;
    const { errors } = formState;

    if (started !== null) {
        return <ChurchStarted started={started} />;
    }

    const sendCode = async () => {
        if (!(await form.trigger('admin_phone'))) {
            return;
        }

        requestCode.mutate(form.getValues('admin_phone'), {
            onSuccess: () => setCodeSent(true),
            onError: (error) => showFieldErrors(error, form.setError, ['admin_phone']),
        });
    };

    const submit = form.handleSubmit((values) => {
        startChurch.mutate(toStartChurchInput(values), {
            onSuccess: setStarted,
            onError: (error) => {
                const shown = showFieldErrors(error, form.setError, FIELDS);

                // "Invalid code" has no field of its own in the response, so it belongs to the code box.
                if (shown.length === 0) {
                    form.setError('otp', { message: error.message });
                }
            },
        });
    });

    const requestFailure = requestCode.error !== null && errors.admin_phone === undefined ? requestCode.error.message : null;

    return (
        <form onSubmit={submit} noValidate className="flex flex-col gap-2">
            <TextField
                id="church_name"
                label="Church name"
                required
                placeholder="Grace Assembly International"
                error={errors.church_name?.message}
                {...register('church_name')}
            />
            <TextField
                id="slug"
                label="Web address (optional)"
                placeholder="grace-assembly"
                autoComplete="off"
                error={errors.slug?.message}
                {...register('slug')}
            />
            <p className="-mt-1 mb-2 text-sm text-muted-foreground">
                Becomes your church's own address. Leave it empty and we will choose one from the church name.
            </p>
            <TextField
                id="denomination"
                label="Denomination (optional)"
                placeholder="Assemblies of God"
                error={errors.denomination?.message}
                {...register('denomination')}
            />
            <TextField
                id="city"
                label="Town or city (optional)"
                placeholder="Kumasi"
                autoComplete="address-level2"
                error={errors.city?.message}
                {...register('city')}
            />
            <TextField
                id="member_number_prefix"
                label="Member number letters"
                required
                placeholder="GRC"
                maxLength={PREFIX_MAX_LENGTH}
                autoComplete="off"
                error={errors.member_number_prefix?.message}
                {...register('member_number_prefix')}
            />
            <p className="-mt-1 mb-2 text-sm text-muted-foreground">Every member number starts with these letters, for example GRC-0042.</p>
            <TextField
                id="admin_name"
                label="Your name"
                required
                autoComplete="name"
                placeholder="Kofi Mensah"
                error={errors.admin_name?.message}
                {...register('admin_name')}
            />
            <TextField
                id="admin_phone"
                label="Your phone number"
                type="tel"
                inputMode="tel"
                required
                autoComplete="tel"
                placeholder="024 123 4567"
                error={errors.admin_phone?.message}
                {...register('admin_phone')}
            />
            <Button type="button" variant="outline" onClick={() => void sendCode()} disabled={requestCode.isPending}>
                {requestCode.isPending ? 'Sending…' : codeSent ? 'Send the code again' : 'Text me a code'}
            </Button>
            {requestFailure !== null && <ErrorNotice message={requestFailure} />}
            {codeSent && (
                <TextField
                    id="otp"
                    label="Code from the text message"
                    required
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    maxLength={OTP_LENGTH}
                    placeholder="123456"
                    error={errors.otp?.message}
                    {...register('otp')}
                />
            )}
            <Button type="submit" className="mt-2" disabled={!codeSent || startChurch.isPending}>
                {startChurch.isPending ? 'Starting…' : 'Start my church'}
            </Button>
        </form>
    );
}
