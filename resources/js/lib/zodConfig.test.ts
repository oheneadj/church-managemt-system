import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { configureZod } from '@/lib/zodConfig';

describe('configureZod', () => {
    it('switches Zod to jitless mode and keeps validating', () => {
        configureZod();

        expect(z.config().jitless).toBe(true);
        expect(z.object({ name: z.string().min(2) }).safeParse({ name: 'A' }).success).toBe(false);
        expect(z.object({ name: z.string().min(2) }).safeParse({ name: 'Ama' }).success).toBe(true);
    });
});
