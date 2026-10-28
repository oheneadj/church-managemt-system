import { useRef, type ChangeEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { PHOTO_MAX_BYTES, PHOTO_TYPES } from '@/constants/photo';
import { useMemberMutations } from '@/hooks/useMemberMutations';

interface PhotoUploadButtonProps {
    memberId: string;
}

/**
 * "Change photo": opens the phone's camera or gallery, checks the file is a JPG or PNG under 5 MB, then uploads it.
 */
export function PhotoUploadButton({ memberId }: PhotoUploadButtonProps) {
    const input = useRef<HTMLInputElement>(null);
    const { uploadPhoto } = useMemberMutations();

    function upload(event: ChangeEvent<HTMLInputElement>) {
        const photo = event.target.files?.[0];
        event.target.value = '';

        if (photo === undefined) {
            return;
        }

        if (!(PHOTO_TYPES as readonly string[]).includes(photo.type)) {
            toast.error('Choose a JPG or PNG photo.');

            return;
        }

        if (photo.size > PHOTO_MAX_BYTES) {
            toast.error('The photo is too big. Choose one under 5 MB.');

            return;
        }

        uploadPhoto.mutate(
            { id: memberId, photo },
            {
                onSuccess: () => toast.success('Photo updated.'),
                onError: (error) => toast.error(error.message),
            },
        );
    }

    return (
        <>
            <Button type="button" variant="outline" size="sm" disabled={uploadPhoto.isPending} onClick={() => input.current?.click()}>
                {uploadPhoto.isPending ? 'Uploading…' : 'Change photo'}
            </Button>
            <input
                ref={input}
                type="file"
                accept={PHOTO_TYPES.join(',')}
                className="sr-only"
                tabIndex={-1}
                aria-label="Member photo file"
                onChange={upload}
            />
        </>
    );
}
