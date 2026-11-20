import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import type { UploadItem } from '@/components/form/ImageUpload';
import { useMemberMutations } from '@/hooks/useMemberMutations';
import { formatFileSize } from '@/utils/formatFileSize';

/**
 * A member's new photo on its way: the one file chosen, its preview, and where it stands, for the image upload to show. Choosing a
 * valid file sends it at once. The progress is the request's own, as the browser reports it, so no number is ever made up; Retry
 * sends the same file again, Cancel stops the request, and closing the screen stops it too. `onUploaded` is called once the server
 * has the photo. The file itself has already been checked by the upload before it gets here, and the server checks it again.
 */
export function useMemberPhotoUpload(memberId: string, onUploaded: () => void) {
    const { uploadPhoto } = useMemberMutations();
    const [item, setItem] = useState<UploadItem | null>(null);
    const file = useRef<File | null>(null);
    const controller = useRef<AbortController | null>(null);
    const preview = useRef<string | null>(null);

    const forgetPreview = () => {
        if (preview.current !== null) {
            URL.revokeObjectURL(preview.current);
            preview.current = null;
        }
    };

    // Leaving the screen stops a photo that is still sending and lets go of its preview.
    useEffect(
        () => () => {
            controller.current?.abort();
            forgetPreview();
        },
        [],
    );

    const send = (photo: File) => {
        const request = new AbortController();
        controller.current = request;
        setItem((now) => (now === null ? now : { ...now, status: 'uploading', progress: undefined, error: undefined }));

        uploadPhoto.mutate(
            {
                id: memberId,
                photo,
                signal: request.signal,
                onProgress: (percent) =>
                    setItem((now) => (now !== null && now.status === 'uploading' ? { ...now, progress: percent } : now)),
            },
            {
                onSuccess: () => {
                    setItem((now) => (now === null ? now : { ...now, status: 'success', progress: 100 }));
                    toast.success('Photo updated.');
                    onUploaded();
                },
                // A cancelled photo has already been cleared, so only a real failure reaches here.
                onError: (error) => setItem((now) => (now === null ? now : { ...now, status: 'error', error: error.message })),
            },
        );
    };

    const choose = (files: File[]) => {
        const photo = files[0];

        if (photo === undefined) {
            return;
        }

        controller.current?.abort();
        forgetPreview();
        preview.current = URL.createObjectURL(photo);
        file.current = photo;
        setItem({ id: 'photo', name: photo.name, sizeText: formatFileSize(photo.size), previewUrl: preview.current, status: 'uploading' });
        send(photo);
    };

    const retry = () => {
        if (file.current !== null) {
            send(file.current);
        }
    };

    const remove = () => {
        controller.current?.abort();
        forgetPreview();
        file.current = null;
        setItem(null);
        uploadPhoto.reset();
    };

    return { items: item === null ? [] : [item], choose, retry, cancel: remove, remove };
}
