import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { CheckboxField } from '@/components/form/CheckboxField';
import { TextField } from '@/components/form/TextField';
import {
    churchSettingsSchema,
    toSettingsInput,
    valuesFromSettings,
    type ChurchSettingsValues,
} from '@/components/Settings/churchSettingsSchema';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ABSENCE_THRESHOLD_MAX, ABSENCE_THRESHOLD_MIN, YOUTH_AGE_LIMIT_MAX, YOUTH_AGE_LIMIT_MIN } from '@/constants/settings';
import { useChurchSettingsMutations } from '@/hooks/useChurchSettings';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { ChurchSettings } from '@/types/church';

const FIELDS = ['youth_age_limit', 'absence_threshold', 'receipt_on_cash', 'absent_alert'] as const;

/**
 * How the church wants things to work: who counts as youth, when a member counts as absent, and the two texts the
 * platform sends for the church by itself (cash receipts and the absence text, which costs SMS credits).
 */
export function ChurchSettingsForm({ settings }: { settings: ChurchSettings }) {
    const { updateSettings } = useChurchSettingsMutations();
    const form = useForm<ChurchSettingsValues>({
        resolver: zodResolver(churchSettingsSchema),
        defaultValues: valuesFromSettings(settings),
    });
    const { errors } = form.formState;

    const save = form.handleSubmit((values) => {
        updateSettings.mutate(toSettingsInput(values), {
            onSuccess: (saved) => {
                form.reset(valuesFromSettings(saved));
                toast.success('Settings saved.');
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
                    <h2>Preferences</h2>
                </CardTitle>
                <CardDescription>Texts sent for you by the platform are paid for from your SMS credits.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={save} noValidate className="flex flex-col gap-3">
                    <TextField
                        id="setting-youth-age"
                        label="Youth are younger than (years)"
                        type="number"
                        inputMode="numeric"
                        min={YOUTH_AGE_LIMIT_MIN}
                        max={YOUTH_AGE_LIMIT_MAX}
                        error={errors.youth_age_limit?.message}
                        {...form.register('youth_age_limit')}
                    />
                    <TextField
                        id="setting-absence"
                        label="A member is absent after missing this many Sunday services in a row"
                        type="number"
                        inputMode="numeric"
                        min={ABSENCE_THRESHOLD_MIN}
                        max={ABSENCE_THRESHOLD_MAX}
                        error={errors.absence_threshold?.message}
                        {...form.register('absence_threshold')}
                    />
                    <CheckboxField
                        id="setting-receipt"
                        label="Text a receipt when a cash gift is recorded"
                        {...form.register('receipt_on_cash')}
                    />
                    <div className="flex flex-col gap-1">
                        <CheckboxField
                            id="setting-absent-alert"
                            label="Text members who have gone absent"
                            {...form.register('absent_alert')}
                        />
                        <p className="ml-7 text-sm text-muted-foreground">
                            Each member is texted once for each stretch of absence, every morning, and it uses your SMS credits. It stops if
                            you run out.
                        </p>
                    </div>
                    <div>
                        <Button type="submit" disabled={updateSettings.isPending}>
                            {updateSettings.isPending ? 'Saving…' : 'Save preferences'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}
