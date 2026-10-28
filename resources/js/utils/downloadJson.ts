/**
 * Saves data as a .json file on the user's device. The link is created and clicked in memory, then released,
 * so nothing is left behind in the page.
 */
export function downloadJson(filename: string, data: unknown): void {
    const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
    const link = document.createElement('a');

    link.href = url;
    link.download = filename;
    link.click();

    URL.revokeObjectURL(url);
}
