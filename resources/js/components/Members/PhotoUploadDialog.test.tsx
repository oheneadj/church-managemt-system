import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PhotoUploadDialog } from '@/components/Members/PhotoUploadDialog';
import { ApiError } from '@/services/ApiError';
import { membersApi } from '@/services/api/members';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/members');

// Changing a member's photo (docs/design-system/foundations.md, 5b; interactions.md, section 17): a dialog with the image upload,
// the request's real progress, success that closes it, failure with Retry, and Cancel that stops the request.

const PHOTO = () => new File(['x'], 'me.jpg', { type: 'image/jpeg' });

/** An upload the test finishes by hand, so it can report progress and then succeed, fail or be cancelled. */
function pendingUpload() {
    const controls: { progress: (percent: number) => void; succeed: () => void; fail: (message: string) => void; signal?: AbortSignal } = {
        progress: () => undefined,
        succeed: () => undefined,
        fail: () => undefined,
    };
    vi.mocked(membersApi.uploadPhoto).mockImplementation(
        (_id, _photo, options) =>
            new Promise((resolve, reject) => {
                controls.signal = options?.signal;
                controls.progress = (percent) => options?.onProgress?.(percent);
                controls.succeed = () => resolve('https://signed.example/photo.jpg');
                controls.fail = (message) => reject(new ApiError(message, 500));
                options?.signal?.addEventListener('abort', () => reject(new DOMException('cancelled', 'AbortError')));
            }),
    );

    return controls;
}

async function openAndChoose() {
    const user = userEvent.setup();
    renderPage(<PhotoUploadDialog memberId="member-1" />, { path: '/x', url: '/x' });
    await user.click(screen.getByRole('button', { name: 'Change photo' }));
    const dialog = await screen.findByRole('dialog', { name: 'Change photo' });
    fireEvent.change(within(dialog).getByLabelText('Member photo'), { target: { files: [PHOTO()] } });

    return { user, dialog };
}

describe('PhotoUploadDialog', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        URL.createObjectURL = vi.fn(() => 'blob:preview');
        URL.revokeObjectURL = vi.fn();
    });

    it('sends the photo at once, showing a preview, its name and size, and no number until the browser reports one', async () => {
        pendingUpload();
        const { dialog } = await openAndChoose();

        expect(await within(dialog).findByText(/me\.jpg/)).toBeInTheDocument();
        expect(within(dialog).getByText('Uploading…')).toBeInTheDocument();
        expect(within(dialog).queryByText(/%/)).toBeNull();
        expect(URL.createObjectURL).toHaveBeenCalledOnce();
    });

    it("shows the request's own progress as it comes in", async () => {
        const upload = pendingUpload();
        const { dialog } = await openAndChoose();
        await within(dialog).findByText('Uploading…');

        upload.progress(40);
        expect(await within(dialog).findByText('40%')).toBeInTheDocument();
        expect(within(dialog).getByRole('progressbar', { name: 'Uploading me.jpg' })).toHaveAttribute('value', '40');

        upload.progress(90);
        expect(await within(dialog).findByText('90%')).toBeInTheDocument();
    });

    it('says the photo is updated and closes the dialog once the server has it', async () => {
        const success = vi.spyOn(toast, 'success').mockReturnValue(1);
        const upload = pendingUpload();
        await openAndChoose();
        await screen.findByText('Uploading…');

        upload.succeed();

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(success).toHaveBeenCalledWith('Photo updated.');
    });

    it('says why it failed, keeps the photo, and sends the same file again on Retry', async () => {
        const upload = pendingUpload();
        const { user, dialog } = await openAndChoose();
        await within(dialog).findByText('Uploading…');

        upload.fail('The server is busy.');
        expect(await within(dialog).findByText('The server is busy.')).toBeInTheDocument();
        expect(within(dialog).getByText(/me\.jpg/)).toBeInTheDocument();
        const first = vi.mocked(membersApi.uploadPhoto).mock.calls[0]?.[1];

        await user.click(within(dialog).getByRole('button', { name: /Retry/ }));

        await waitFor(() => expect(membersApi.uploadPhoto).toHaveBeenCalledTimes(2));
        expect(vi.mocked(membersApi.uploadPhoto).mock.calls[1]?.[1]).toBe(first);
        expect(await within(dialog).findByText('Uploading…')).toBeInTheDocument();
    });

    it('stops the request and removes the photo on Cancel, without calling it a failure', async () => {
        const upload = pendingUpload();
        const { user, dialog } = await openAndChoose();
        await within(dialog).findByText('Uploading…');

        await user.click(within(dialog).getByRole('button', { name: 'Cancel' }));

        expect(upload.signal?.aborted).toBe(true);
        expect(within(dialog).queryByText(/me\.jpg/)).toBeNull();
        expect(within(dialog).queryByText(/could not/i)).toBeNull();
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview');
    });

    it('stops a photo that is still sending when the dialog is closed, and lets go of its preview', async () => {
        const upload = pendingUpload();
        const { user, dialog } = await openAndChoose();
        await within(dialog).findByText('Uploading…');

        await user.click(within(dialog).getByRole('button', { name: 'Close' }));

        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
        expect(upload.signal?.aborted).toBe(true);
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:preview');
    });

    it('starts empty each time it is opened, so an earlier photo never lingers', async () => {
        const upload = pendingUpload();
        const { user } = await openAndChoose();
        await screen.findByText('Uploading…');
        upload.succeed();
        await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

        await user.click(screen.getByRole('button', { name: 'Change photo' }));
        const again = await screen.findByRole('dialog', { name: 'Change photo' });

        expect(within(again).queryByText(/me\.jpg/)).toBeNull();
        expect(within(again).getByText('JPEG or PNG, up to 5 MB.')).toBeInTheDocument();
    });

    it('replaces a photo that is sending when another is chosen, stopping the first', async () => {
        const upload = pendingUpload();
        const { dialog } = await openAndChoose();
        await within(dialog).findByText('Uploading…');
        const firstSignal = upload.signal;

        fireEvent.change(within(dialog).getByLabelText('Member photo'), {
            target: { files: [new File(['y'], 'other.png', { type: 'image/png' })] },
        });

        await waitFor(() => expect(membersApi.uploadPhoto).toHaveBeenCalledTimes(2));
        expect(firstSignal?.aborted).toBe(true);
        expect(await within(dialog).findByText(/other\.png/)).toBeInTheDocument();
        expect(within(dialog).queryByText(/me\.jpg/)).toBeNull();
        // The first request being stopped must not mark the new photo as failed.
        await waitFor(() => expect(within(dialog).getByText('Uploading…')).toBeInTheDocument());
        expect(within(dialog).queryByText('cancelled')).toBeNull();
        expect(within(dialog).queryByRole('button', { name: /Retry/ })).toBeNull();
    });
});
