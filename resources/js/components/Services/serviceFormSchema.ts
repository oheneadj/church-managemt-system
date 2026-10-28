import { z } from 'zod';
import { SERVICE_TYPES, type ServiceType } from '@/constants/enums';
import type { ServiceInput } from '@/types/services';

const TYPE_VALUES = SERVICE_TYPES.map((option) => option.value) as [ServiceType, ...ServiceType[]];

/** The checks on the new-service form. Times are optional; when both are given the end must come after the start. */
export const serviceFormSchema = z
    .object({
        name: z.string().trim().min(1, 'Enter the name of the service').max(255, 'Keep it under 255 characters'),
        type: z.enum(TYPE_VALUES, { error: 'Choose a type' }),
        service_date: z.string().min(1, 'Choose the date'),
        starts_at: z.string(),
        ends_at: z.string(),
        notes: z.string().trim().max(2000, 'Keep it under 2000 characters'),
    })
    .refine((values) => values.starts_at === '' || values.ends_at === '' || values.ends_at > values.starts_at, {
        error: 'The end must be after the start',
        path: ['ends_at'],
    });

export type ServiceFormValues = z.infer<typeof serviceFormSchema>;

/** Turns the form values into the request body: empty optional boxes are left out. */
export function toServiceInput(values: ServiceFormValues): ServiceInput {
    return {
        name: values.name,
        type: values.type,
        service_date: values.service_date,
        starts_at: values.starts_at || undefined,
        ends_at: values.ends_at || undefined,
        notes: values.notes || undefined,
    };
}
