/**
 * The first letters of the first and last word of a name, for an avatar when there is no photo.
 * "Emmanuel Kwasi Mensah-Boateng" gives "EM"; a single name gives one letter.
 */
export function initialsOf(fullName: string): string {
    const words = fullName.trim().split(/\s+/).filter(Boolean);

    if (words.length === 0) {
        return '';
    }

    const first = words[0] ?? '';
    const last = words.length > 1 ? (words[words.length - 1] ?? '') : '';

    return (first.charAt(0) + last.charAt(0)).toUpperCase();
}
