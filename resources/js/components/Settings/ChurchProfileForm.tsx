import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { TextAreaField } from '@/components/form/TextAreaField';
import { TextField } from '@/components/form/TextField';
import {
    churchProfileSchema,
    toProfileInput,
    valuesFromProfile,
    type ChurchProfileValues,
} from '@/components/Settings/churchProfileSchema';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useChurchSettingsMutations } from '@/hooks/useChurchSettings';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { ChurchProfile } from '@/types/church';

const FIELDS = ['name', 'denomination', 'address', 'city', 'region', 'phone', 'email'] as const;

/**
 * The church's own details: name, denomination, address, phone and email. The web address and time zone are not here,
 * because they are not the church's to change (docs/08). Saving sends every box; the server records only what changed.
 */
export function ChurchProfileForm({ profile }: { profile: ChurchProfile }) {
    const { updateProfile } = useChurchSettingsMutations();
    const form = useForm<ChurchProfileValues>({ resolver: zodResolver(churchProfileSchema), defaultValues: valuesFromProfile(profile) });
    const { errors } = form.formState;

    const save = form.handleSubmit((values) => {
        updateProfile.mutate(toProfileInput(values), {
            onSuccess: (saved) => {
                form.reset(valuesFromProfile(saved));
                toast.success('Church details saved.');
            },
            onError: (error) => {
                if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
                    toast.error(error.message);
                }
            },
        });
    });

    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <h2>Church details</h2>
                </CardTitle>
                <CardDescription>Your web address is {profile.slug} and cannot be changed here.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="church-name"
                        label="Name of the church"
                        required
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <TextField
                        id="church-denomination"
                        label="Denomination"
                        error={errors.denomination?.message}
                        {...form.register('denomination')}
                    />
                    <TextAreaField id="church-address" label="Address" error={errors.address?.message} {...form.register('address')} />
                    <div className="grid gap-2 sm:grid-cols-2">
                        <TextField id="church-city" label="Town or city" error={errors.city?.message} {...form.register('city')} />
                        <TextField id="church-region" label="Region" error={errors.region?.message} {...form.register('region')} />
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2">
                        <TextField
                            id="church-phone"
                            label="Phone number"
                            type="tel"
                            inputMode="tel"
                            autoComplete="off"
                            placeholder="030 200 0000"
                            error={errors.phone?.message}
                            {...form.register('phone')}
                        />
                        <TextField
                            id="church-email"
                            label="Email address"
                            type="email"
                            inputMode="email"
                            autoComplete="off"
                            error={errors.email?.message}
                            {...form.register('email')}
                        />
                    </div>
                    <div>
                        <Button type="submit" disabled={updateProfile.isPending}>
                            {updateProfile.isPending ? 'Saving…' : 'Save details'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
