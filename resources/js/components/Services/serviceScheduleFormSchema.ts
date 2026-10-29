import { z } from 'zod';
import { SERVICE_TYPES, WEEKDAYS, type ServiceType, type Weekday } from '@/constants/enums';
import type { ServiceSchedule, ServiceScheduleInput } from '@/types/services';

const TYPE_VALUES = SERVICE_TYPES.map((option) => option.value) as [ServiceType, ...ServiceType[]];
const WEEKDAY_VALUES = WEEKDAYS.map((option) => option.value) as [Weekday, ...Weekday[]];

/** The checks on the weekly schedule form. Times are optional; when both are given the end must come after the start. */
export const serviceScheduleFormSchema = z
    .object({
        name: z.string().trim().min(1, 'Enter the name of the service').max(255, 'Keep it under 255 characters'),
        type: z.enum(TYPE_VALUES, { error: 'Choose a type' }),
        weekday: z.enum(WEEKDAY_VALUES, { error: 'Choose the day of the week' }),
        starts_at: z.string(),
        ends_at: z.string(),
    })
    .refine((values) => values.starts_at === '' || values.ends_at === '' || values.ends_at > values.starts_at, {
        error: 'The end must be after the start',
        path: ['ends_at'],
    });

export type ServiceScheduleFormValues = z.infer<typeof serviceScheduleFormSchema>;

/** A new schedule starts on a Sunday morning service, the most common one. */
export function blankScheduleValues(): ServiceScheduleFormValues {
    return { name: '', type: 'sunday', weekday: 'sunday', starts_at: '', ends_at: '' };
}

/** The form filled in from a saved schedule. */
export function valuesFromSchedule(schedule: ServiceSchedule): ServiceScheduleFormValues {
    return {
        name: schedule.name,
        type: schedule.type,
        weekday: schedule.weekday,
        starts_at: schedule.starts_at ?? '',
        ends_at: schedule.ends_at ?? '',
    };
}

/** Turns the form values into the request body: an empty time is sent as null so a time can be cleared. */
export function toScheduleInput(values: ServiceScheduleFormValues): ServiceScheduleInput {
    return {
        name: values.name,
        type: values.type,
        weekday: values.weekday,
        starts_at: values.starts_at || null,
        ends_at: values.ends_at || null,
    };
}
