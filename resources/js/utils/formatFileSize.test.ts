import { describe, expect, it } from 'vitest';
import { formatFileSize } from '@/utils/formatFileSize';

describe('formatFileSize', () => {
    it.each([
        [0, '0 bytes'],
        [1, '1 byte'],
        [900, '900 bytes'],
        [1024, '1 KB'],
        [348_160, '340 KB'],
        [1536, '1.5 KB'],
        [1024 * 1024, '1 MB'],
        [6_500_000, '6.2 MB'],
        [5 * 1024 * 1024, '5 MB'],
        [250 * 1024 * 1024, '250 MB'],
    ])('writes %d bytes as %s', (bytes, text) => {
        expect(formatFileSize(bytes)).toBe(text);
    });
});
