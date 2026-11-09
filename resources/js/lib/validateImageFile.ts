import { formatFileSize } from '@/utils/formatFileSize';

export interface ImageRules {
    /** The accepted types, such as `image/jpeg`. */
    types: readonly string[];
    maxBytes: number;
}

/** What people call each type. A type not listed is shown by its part after the slash, in capitals. */
const TYPE_NAMES: Record<string, string> = { 'image/jpeg': 'JPEG', 'image/png': 'PNG', 'image/webp': 'WebP', 'image/gif': 'GIF' };

const nameOf = (type: string) => TYPE_NAMES[type] ?? type.split('/')[1]?.toUpperCase() ?? type;

/** The accepted types as a sentence: "a JPEG or PNG image", "a JPEG, PNG or WebP image". */
export function acceptedTypesText(types: readonly string[]): string {
    const names = types.map(nameOf);
    const list = names.length > 1 ? `${names.slice(0, -1).join(', ')} or ${names.at(-1)}` : (names[0] ?? 'image');

    return `${list}`;
}

/** The rule shown beside an upload so nobody has to guess: "JPEG or PNG, up to 5 MB." */
export function imageRulesText(rules: ImageRules): string {
    return `${acceptedTypesText(rules.types)}, up to ${formatFileSize(rules.maxBytes)}.`;
}

/**
 * Checks one chosen file against the rules before anything is sent, so a person learns at once and a server is not asked. The
 * server checks again and is what really enforces them. Returns a plain sentence saying what is wrong and what is allowed, or null
 * when the file is fine.
 */
export function validateImageFile(file: File, rules: ImageRules): string | null {
    if (!rules.types.includes(file.type)) {
        return `${file.name} is not an accepted image. Use ${acceptedTypesText(rules.types)}.`;
    }

    if (file.size === 0) {
        return `${file.name} is empty. Choose another image.`;
    }

    if (file.size > rules.maxBytes) {
        return `${file.name} is ${formatFileSize(file.size)}. The most allowed is ${formatFileSize(rules.maxBytes)}.`;
    }

    return null;
}
