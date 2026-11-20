import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { SelectField } from '@/components/form/SelectField';
import { TextField } from '@/components/form/TextField';
import {
    blankScheduleValues,
    serviceScheduleFormSchema,
    toScheduleInput,
    valuesFromSchedule,
    type ServiceScheduleFormValues,
} from '@/components/Services/serviceScheduleFormSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { SERVICE_TYPES, WEEKDAYS } from '@/constants/enums';
import { useServiceScheduleMutations } from '@/hooks/useServiceSchedules';
import { newIdempotencyKey } from '@/lib/idempotencyKey';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { ServiceSchedule } from '@/types/services';

interface ServiceScheduleDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** The schedule to change. Leave out to set a new one up. */
    schedule?: ServiceSchedule;
}

const FIELDS = ['name', 'type', 'weekday', 'starts_at', 'ends_at'] as const;

/**
 * A small form to set up a weekly schedule, or to change one. Setting up makes the next weeks of services at once;
 * changing carries the new name, times and day to the future services nobody has touched.
 */
export function ServiceScheduleDialog({ open, onOpenChange, schedule }: ServiceScheduleDialogProps) {
    const { create, update } = useServiceScheduleMutations();
    const form = useForm<ServiceScheduleFormValues>({
        resolver: zodResolver(serviceScheduleFormSchema),
        defaultValues: schedule === undefined ? blankScheduleValues() : valuesFromSchedule(schedule),
    });
    const { errors } = form.formState;
    // One key for one attempt to set a schedule up: a double click or a retry sets up one, and a new key follows each success.
    const [idempotencyKey, setIdempotencyKey] = useState(newIdempotencyKey);
    const pending = create.isPending || update.isPending;

    useEffect(() => {
        if (open) {
            form.reset(schedule === undefined ? blankScheduleValues() : valuesFromSchedule(schedule));
        }
    }, [open, schedule, form]);

    const done = (message: string) => {
        toast.success(message);
        setIdempotencyKey(newIdempotencyKey());
        onOpenChange(false);
    };
    const failed = (error: Error) => {
        if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
            toast.error(error.message);
        }
    };

    const submit = form.handleSubmit((values) => {
        const input = toScheduleInput(values);

        if (schedule === undefined) {
            create.mutate(
                { input, idempotencyKey },
                { onSuccess: () => done('Schedule set up. The next weeks are on the calendar.'), onError: failed },
            );
        } else {
            update.mutate({ id: schedule.id, input }, { onSuccess: () => done('Schedule changed.'), onError: failed });
        }
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{schedule === undefined ? 'New weekly schedule' : 'Change schedule'}</DialogTitle>
                    <DialogDescription>
                        {schedule === undefined
                            ? 'The service is made for you every week, eight weeks ahead.'
                            : 'The change reaches the coming services nobody has opened, and not ones you changed yourself.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="schedule-name"
                        label="Name"
                        required
                        placeholder="Sunday Morning Service"
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <SelectField
                        id="schedule-type"
                        label="Type"
                        required
                        options={SERVICE_TYPES}
                        error={errors.type?.message}
                        {...form.register('type')}
                    />
                    <SelectField
                        id="schedule-weekday"
                        label="Every"
                        required
                        options={WEEKDAYS}
                        error={errors.weekday?.message}
                        {...form.register('weekday')}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <TextField
                            id="schedule-starts"
                            label="Starts (optional)"
                            type="time"
                            error={errors.starts_at?.message}
                            {...form.register('starts_at')}
                        />
                        <TextField
                            id="schedule-ends"
                            label="Ends (optional)"
                            type="time"
                            error={errors.ends_at?.message}
                            {...form.register('ends_at')}
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={pending}>
                            {pending ? 'Saving…' : schedule === undefined ? 'Set up schedule' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
