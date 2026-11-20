const KILO = 1024;

/** A file size as people read it: "340 KB", "6.2 MB". Powers of 1024, as the operating system shows them; one decimal only when it helps. */
export function formatFileSize(bytes: number): string {
    if (bytes < KILO) {
        return `${bytes} ${bytes === 1 ? 'byte' : 'bytes'}`;
    }

    const [value, unit] = bytes < KILO * KILO ? [bytes / KILO, 'KB'] : [bytes / (KILO * KILO), 'MB'];

    return `${Number.isInteger(value) || value >= 100 ? Math.round(value) : value.toFixed(1)} ${unit}`;
}
