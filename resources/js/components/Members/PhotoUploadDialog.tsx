import { useState } from 'react';
import { ImageUpload } from '@/components/form/ImageUpload';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { PHOTO_MAX_BYTES, PHOTO_TYPES } from '@/constants/photo';
import { useMemberPhotoUpload } from '@/hooks/useMemberPhotoUpload';

interface PhotoUploadBodyProps {
    memberId: string;
    onUploaded: () => void;
}

/** The upload itself. It is made fresh each time the dialog opens, so an earlier photo never lingers, and it stops a photo still sending when the dialog closes. */
function PhotoUploadBody({ memberId, onUploaded }: PhotoUploadBodyProps) {
    const upload = useMemberPhotoUpload(memberId, onUploaded);

    return (
        <ImageUpload
            label="Member photo"
            types={PHOTO_TYPES}
            maxBytes={PHOTO_MAX_BYTES}
            items={upload.items}
            onFiles={upload.choose}
            onRemove={upload.remove}
            onRetry={upload.retry}
            onCancel={upload.cancel}
        />
    );
}

/**
 * "Change photo": a dialog with the image upload. The rules are shown up front, the chosen photo is checked before anything is sent,
 * it uploads at once with its real progress, and the dialog closes when the server has it. Needs the edit permission, which the
 * page checks before drawing this.
 */
export function PhotoUploadDialog({ memberId }: { memberId: string }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    Change photo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change photo</DialogTitle>
                    <DialogDescription>Choose a clear photo of their face. It replaces the one now shown.</DialogDescription>
                </DialogHeader>
                <PhotoUploadBody memberId={memberId} onUploaded={() => setOpen(false)} />
            </DialogContent>
        </Dialog>
    );
}
