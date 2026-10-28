import { describe, expect, it } from 'vitest';
import { initialsOf } from '@/utils/initialsOf';

describe('initialsOf', () => {
    it('uses the first and last word of a name', () => {
        expect(initialsOf('Emmanuel Kwasi Mensah-Boateng')).toBe('EM');
    });

    it('gives one letter for a single name', () => {
        expect(initialsOf('Abena')).toBe('A');
    });

    it('ignores extra spaces and gives nothing for an empty name', () => {
        expect(initialsOf('  abena   asante ')).toBe('AA');
        expect(initialsOf('   ')).toBe('');
    });
});
