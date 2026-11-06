import { z } from 'zod';
import { PASSWORD_MAX_BYTES, PASSWORD_MIN_LENGTH } from '@/constants/password';

/**
 * The rules for choosing a staff password, checked in the browser first (the server checks again, D-054).
 * Length is counted in bytes at the top end because the hashing method ignores everything after byte 72.
 */
export const newPasswordField = z
    .string()
    .min(PASSWORD_MIN_LENGTH, `Use at least ${PASSWORD_MIN_LENGTH} characters`)
    .refine((password) => new TextEncoder().encode(password).length <= PASSWORD_MAX_BYTES, { error: 'That password is too long' });
