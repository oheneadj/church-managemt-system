import { describe, expect, it } from 'vitest';
import { acceptedTypesText, imageRulesText, validateImageFile } from '@/lib/validateImageFile';

const RULES = { types: ['image/jpeg', 'image/png'], maxBytes: 5 * 1024 * 1024 } as const;

/** A file of a given type and size, without making a real one that big. */
function file(name: string, type: string, size: number): File {
    const made = new File(['x'], name, { type });
    Object.defineProperty(made, 'size', { value: size });

    return made;
}

describe('validateImageFile', () => {
    it('accepts a file of an accepted type within the size', () => {
        expect(validateImageFile(file('photo.png', 'image/png', 340_000), RULES)).toBeNull();
        expect(validateImageFile(file('photo.jpg', 'image/jpeg', RULES.maxBytes), RULES)).toBeNull();
    });

    it('refuses another type, saying what is accepted', () => {
        expect(validateImageFile(file('photo.gif', 'image/gif', 1000), RULES)).toBe('photo.gif is not an accepted image. Use JPEG or PNG.');
        expect(validateImageFile(file('notes.pdf', 'application/pdf', 1000), RULES)).toBe(
            'notes.pdf is not an accepted image. Use JPEG or PNG.',
        );
    });

    it('refuses a file that is too big, saying how big it is and the most allowed', () => {
        expect(validateImageFile(file('big.png', 'image/png', 6_500_000), RULES)).toBe('big.png is 6.2 MB. The most allowed is 5 MB.');
        expect(validateImageFile(file('edge.png', 'image/png', RULES.maxBytes + 1), RULES)).not.toBeNull();
    });

    it('refuses an empty file', () => {
        expect(validateImageFile(file('empty.png', 'image/png', 0), RULES)).toBe('empty.png is empty. Choose another image.');
    });

    it('checks the type before the size', () => {
        expect(validateImageFile(file('big.gif', 'image/gif', 99_000_000), RULES)).toContain('not an accepted image');
    });
});

describe('the rules as words', () => {
    it.each([
        [['image/jpeg'], 'JPEG'],
        [['image/jpeg', 'image/png'], 'JPEG or PNG'],
        [['image/jpeg', 'image/png', 'image/webp'], 'JPEG, PNG or WebP'],
        [['image/avif'], 'AVIF'],
        [[], 'image'],
    ])('lists %j as "%s"', (types, text) => {
        expect(acceptedTypesText(types)).toBe(text);
    });

    it('says the whole rule beside an upload', () => {
        expect(imageRulesText(RULES)).toBe('JPEG or PNG, up to 5 MB.');
    });
});
