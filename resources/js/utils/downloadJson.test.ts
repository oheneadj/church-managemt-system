import { afterEach, describe, expect, it, vi } from 'vitest';
import { downloadJson } from '@/utils/downloadJson';

describe('downloadJson', () => {
    afterEach(() => vi.restoreAllMocks());

    it('clicks a link to a JSON file with the given name and then releases it', () => {
        const createObjectURL = vi.fn(() => 'blob:fake');
        const revokeObjectURL = vi.fn();
        Object.assign(URL, { createObjectURL, revokeObjectURL });
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
        let download = '';
        click.mockImplementation(function (this: HTMLAnchorElement) {
            download = this.download;
        });

        downloadJson('GRC-0001-data.json', { a: 1 });

        expect(download).toBe('GRC-0001-data.json');
        expect(createObjectURL).toHaveBeenCalledOnce();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:fake');
    });
});
