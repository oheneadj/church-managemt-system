import { z } from 'zod';

/** The reason typed when a gift is voided: it is required, so every void can be explained later (docs/04, Corrections and Voids). */
export const voidReasonSchema = z.object({
    reason: z.string().trim().min(3, 'Say why this gift is being voided').max(2000, 'Keep it under 2000 characters'),
});

export type VoidReasonValues = z.infer<typeof voidReasonSchema>;
