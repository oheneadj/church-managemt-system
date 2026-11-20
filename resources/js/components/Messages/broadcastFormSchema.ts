import { z } from 'zod';
import { MEMBER_STATUSES } from '@/constants/enums';
import type { BroadcastInput } from '@/types/messages';

/** The member group a broadcast goes to unless the person chooses another (docs/08: all active members). */
export const DEFAULT_AUDIENCE_STATUS = 'active';

/** The checks on the compose form. The part limit and who is reached are the server's to judge; the estimate shows them. */
export const broadcastFormSchema = z.object({
    message: z.string().trim().min(1, 'Write the message'),
    status: z.string().refine((status) => MEMBER_STATUSES.some((option) => option.value === status), 'Choose who it goes to'),
    department_id: z.string(),
});

export type BroadcastFormValues = z.infer<typeof broadcastFormSchema>;

/** A new broadcast goes to all active members. */
export function blankBroadcastValues(): BroadcastFormValues {
    return { message: '', status: DEFAULT_AUDIENCE_STATUS, department_id: '' };
}

/** Turns the form into what the estimate and the send take. A department that is not chosen is left out. */
export function toBroadcastInput(values: BroadcastFormValues): BroadcastInput {
    const message = values.message.trim();

    return {
        channel: 'sms',
        message,
        recipient_filter:
            values.department_id === '' ? { status: values.status } : { status: values.status, department_id: values.department_id },
    };
}
