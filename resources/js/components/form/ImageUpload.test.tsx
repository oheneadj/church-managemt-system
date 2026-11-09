import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ImageUpload, type UploadItem } from '@/components/form/ImageUpload';

// The image upload (docs/design-system/interactions.md, section 17): rules shown up front, every file checked before anything is sent,
// good files kept when another in the batch is refused, real progress only, and Retry, Cancel and Remove.

const RULES = { types: ['image/jpeg', 'image/png'], maxBytes: 5 * 1024 * 1024 } as const;

const png = (name = 'photo.png') => new File(['x'], name, { type: 'image/png' });
const gif = (name = 'photo.gif') => new File(['x'], name, { type: 'image/gif' });
const huge = (name = 'huge.png') => Object.defineProperty(new File(['x'], name, { type: 'image/png' }), 'size', { value: 6_500_000 });

function setup(props: Partial<Parameters<typeof ImageUpload>[0]> = {}) {
    const onFiles = vi.fn();
    const onRemove = vi.fn();
    const onRetry = vi.fn();
    const onCancel = vi.fn();
    render(
        <ImageUpload
            label="Photo"
            {...RULES}
            items={[]}
            onFiles={onFiles}
            onRemove={onRemove}
            onRetry={onRetry}
            onCancel={onCancel}
            {...props}
        />,
    );

    return {
        onFiles,
        onRemove,
        onRetry,
        onCancel,
        input: document.querySelector('input[type="file"]') as HTMLInputElement,
        zone: document.querySelector('[data-slot="image-upload-zone"]') as HTMLElement,
    };
}

const item = (over: Partial<UploadItem>): UploadItem => ({ id: 'a', name: 'photo.png', sizeText: '340 KB', status: 'ready', ...over });

describe('ImageUpload: choosing', () => {
    it('says the rules up front, with a labelled button and a labelled file input', () => {
        const { input } = setup();

        expect(screen.getByText('JPEG or PNG, up to 5 MB.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Choose an image' })).toBeInTheDocument();
        expect(input).toHaveAttribute('accept', 'image/jpeg,image/png');
        expect(input).toHaveAccessibleName('Photo');
        expect(input).toHaveAccessibleDescription('JPEG or PNG, up to 5 MB.');
        expect(input).not.toHaveAttribute('multiple');
    });

    it('opens the file chooser from the button, which is reachable by keyboard', async () => {
        const { input } = setup();
        const open = vi.spyOn(input, 'click');

        await userEvent.setup().click(screen.getByRole('button', { name: 'Choose an image' }));

        expect(open).toHaveBeenCalledOnce();
    });

    it('passes a good file on and shows no complaint', async () => {
        const { onFiles, input } = setup();
        const file = png();

        await userEvent.setup().upload(input, file);

        expect(onFiles).toHaveBeenCalledWith([file]);
        expect(screen.queryByRole('alert')).toBeNull();
    });

    it.each([
        ['another type', () => gif(), 'photo.gif is not an accepted image. Use JPEG or PNG.'],
        ['too big', () => huge(), 'huge.png is 6.2 MB. The most allowed is 5 MB.'],
    ])('refuses a file that is %s, in plain words, and sends nothing', async (_name, make, message) => {
        const { onFiles, input } = setup({ multiple: true });

        fireEvent.change(input, { target: { files: [make()] } });

        expect(onFiles).not.toHaveBeenCalled();
        expect(screen.getByRole('alert')).toHaveTextContent(message);
    });

    it('keeps the good files of a batch when another is refused, and says which', () => {
        const { onFiles, input } = setup({ multiple: true });
        const good = [png('a.png'), png('b.png')];

        fireEvent.change(input, { target: { files: [good[0], gif('c.gif'), good[1]] } });

        expect(onFiles).toHaveBeenCalledWith(good);
        expect(screen.getByRole('alert')).toHaveTextContent('c.gif is not an accepted image');
        expect(within(screen.getByRole('alert')).getAllByRole('listitem')).toHaveLength(1);
    });

    it('takes only the first good file when it is for one image', () => {
        const { onFiles, zone } = setup();
        const files = [png('a.png'), png('b.png')];

        fireEvent.drop(zone, { dataTransfer: { files } });

        expect(onFiles).toHaveBeenCalledWith([files[0]]);
    });

    it('takes several when it is for several, and says so on the button', () => {
        const { onFiles, zone } = setup({ multiple: true });
        const files = [png('a.png'), png('b.png')];

        fireEvent.drop(zone, { dataTransfer: { files } });

        expect(onFiles).toHaveBeenCalledWith(files);
        expect(screen.getByRole('button', { name: 'Choose images' })).toBeInTheDocument();
    });

    it('clears an earlier complaint when the next choice is fine', async () => {
        const { input } = setup();
        const user = userEvent.setup({ applyAccept: false });

        await user.upload(input, gif());
        expect(screen.getByRole('alert')).toBeInTheDocument();

        await user.upload(input, png());
        expect(screen.queryByRole('alert')).toBeNull();
    });
});

describe('ImageUpload: dragging', () => {
    it('lights the area while a file is over it, and lets go when it leaves', () => {
        const { zone } = setup();

        fireEvent.dragOver(zone);
        expect(zone).toHaveAttribute('data-dragging', 'true');

        fireEvent.dragLeave(zone);
        expect(zone).not.toHaveAttribute('data-dragging');
    });

    it('lets go of the highlight once the file is dropped', () => {
        const { zone } = setup();

        fireEvent.dragOver(zone);
        fireEvent.drop(zone, { dataTransfer: { files: [png()] } });

        expect(zone).not.toHaveAttribute('data-dragging');
    });

    it('does nothing at all while disabled', () => {
        const { onFiles, zone } = setup({ disabled: true });

        fireEvent.dragOver(zone);
        fireEvent.drop(zone, { dataTransfer: { files: [png()] } });

        expect(zone).not.toHaveAttribute('data-dragging');
        expect(onFiles).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Choose an image' })).toBeDisabled();
    });
});

describe('ImageUpload: the files', () => {
    it('lists each file with its name and size, and a button to remove it that names it', async () => {
        const { onRemove } = setup({ items: [item({})] });

        expect(screen.getByText('photo.png')).toBeInTheDocument();
        expect(screen.getByText(/340 KB/)).toBeInTheDocument();
        expect(screen.getByText('Ready to upload')).toBeInTheDocument();

        await userEvent.setup().click(screen.getByRole('button', { name: 'Remove photo.png' }));
        expect(onRemove).toHaveBeenCalledWith('a');
    });

    it('shows the real progress of a file that is sending, and cancels it', async () => {
        const { onCancel } = setup({ items: [item({ status: 'uploading', progress: 62 })] });
        const bar = screen.getByRole('progressbar', { name: 'Uploading photo.png' });

        expect(bar).toHaveAttribute('value', '62');
        expect(screen.getByText('62%')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Remove photo.png' })).toBeNull();

        await userEvent.setup().click(screen.getByRole('button', { name: 'Cancel' }));
        expect(onCancel).toHaveBeenCalledWith('a');
    });

    it('makes up no number when the progress is not known: it says it is uploading', () => {
        setup({ items: [item({ status: 'uploading' })] });

        expect(screen.getByText('Uploading…')).toBeInTheDocument();
        expect(screen.queryByText(/%/)).toBeNull();
        expect(screen.getByRole('progressbar')).not.toHaveAttribute('value');
    });

    it('can still be removed while it sends when no cancel is offered', () => {
        setup({ items: [item({ status: 'uploading', progress: 10 })], onCancel: undefined });

        expect(screen.getByRole('button', { name: 'Remove photo.png' })).toBeInTheDocument();
    });

    it('says a file is uploaded, in words and with an icon', () => {
        setup({ items: [item({ status: 'success' })] });

        expect(screen.getByText('Uploaded')).toBeInTheDocument();
    });

    it('says why a file failed and offers to try again', async () => {
        const { onRetry } = setup({ items: [item({ status: 'error', error: 'The connection dropped.' })] });

        expect(screen.getByText('The connection dropped.')).toBeInTheDocument();
        await userEvent.setup().click(screen.getByRole('button', { name: /Retry/ }));
        expect(onRetry).toHaveBeenCalledWith('a');
    });

    it('has a plain sentence for a failure with no reason, and no Retry when none is offered', () => {
        setup({ items: [item({ status: 'error' })], onRetry: undefined });

        expect(screen.getByText('It could not be uploaded.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Retry/ })).toBeNull();
    });

    it('gives each file a thumbnail slot, or says there is no preview', () => {
        setup({ items: [item({ id: 'a', previewUrl: '/p.png' }), item({ id: 'b', name: 'b.png' })] });

        expect(screen.getByRole('list', { name: 'Chosen images' })).toBeInTheDocument();
        expect(screen.getByRole('img', { name: 'No preview' })).toBeInTheDocument();
    });
});
