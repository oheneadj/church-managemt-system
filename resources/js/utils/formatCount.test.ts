import { describe, expect, it } from 'vitest';
import { formatCount } from '@/utils/formatCount';

describe('formatCount', () => {
    it('writes a whole number with thousands separators', () => {
        expect(formatCount(0)).toBe('0');
        expect(formatCount(420)).toBe('420');
        expect(formatCount(12500)).toBe('12,500');
    });
});
