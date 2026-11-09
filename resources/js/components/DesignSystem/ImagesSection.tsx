import { useEffect, useRef, useState } from 'react';
import { demoLandscape, demoPortrait } from '@/components/DesignSystem/demoImages';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { ImageUpload, type UploadItem } from '@/components/form/ImageUpload';
import { AvatarGroup, AvatarGroupCount } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Picture } from '@/components/ui/Picture';
import { ProfileImage, type ProfileImageSize } from '@/components/ui/ProfileImage';
import { formatFileSize } from '@/utils/formatFileSize';

const RULES = { types: ['image/jpeg', 'image/png'], maxBytes: 5 * 1024 * 1024 } as const;
const SIZES: ProfileImageSize[] = ['xs', 'sm', 'md', 'lg', 'xl', '2xl'];
const PEOPLE = ['Ama Mensah', 'Kofi Boateng', 'Esi Owusu', 'Yaw Darko', 'Abena Asante'];
const SLOW_LOAD_MS = 1500;

/** What each upload state looks like. These are fixed previews of the component's states, not real uploads. */
const STATE_PREVIEWS: UploadItem[] = [
    { id: 'p1', name: 'ready.png', sizeText: '340 KB', previewUrl: demoLandscape('forest'), status: 'ready' },
    { id: 'p2', name: 'sending.png', sizeText: '2.1 MB', previewUrl: demoLandscape('gold'), status: 'uploading', progress: 62 },
    { id: 'p3', name: 'waiting.png', sizeText: '1.4 MB', previewUrl: demoLandscape('blue'), status: 'uploading' },
    { id: 'p4', name: 'done.png', sizeText: '780 KB', previewUrl: demoLandscape('forest'), status: 'success' },
    {
        id: 'p5',
        name: 'failed.png',
        sizeText: '3.8 MB',
        previewUrl: demoLandscape('gold'),
        status: 'error',
        error: 'The connection dropped. Check it and try again.',
    },
];

/** A picture that arrives late, to show the skeleton and the fade. Pressing the button starts it again. */
function SlowPicture() {
    const [src, setSrc] = useState<string | null>(demoLandscape('forest'));
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => () => void (timer.current && clearTimeout(timer.current)), []);

    const reload = () => {
        setSrc(null);
        timer.current = setTimeout(() => setSrc(demoLandscape('forest')), SLOW_LOAD_MS);
    };

    return (
        <div className="flex flex-col gap-2">
            <Picture
                src={src}
                alt="Green hills under a gold sun"
                fallbackText="Loading the picture…"
                caption="Loads late: skeleton, then fade"
            />
            <Button size="sm" variant="outline" onClick={reload}>
                Load it again
            </Button>
        </div>
    );
}

/** The live upload: choosing a file checks it and shows a preview, and nothing is sent anywhere. */
function LiveUpload() {
    const [items, setItems] = useState<UploadItem[]>([]);

    useEffect(() => () => items.forEach((item) => item.previewUrl && URL.revokeObjectURL(item.previewUrl)), [items]);

    const add = (files: File[]) =>
        setItems((now) => [
            ...now,
            ...files.map((file) => ({
                id: `${file.name}-${file.size}-${now.length}`,
                name: file.name,
                sizeText: formatFileSize(file.size),
                previewUrl: URL.createObjectURL(file),
                status: 'ready' as const,
            })),
        ]);

    return (
        <ImageUpload
            label="Choose an image (checked here, not sent)"
            {...RULES}
            multiple
            items={items}
            onFiles={add}
            onRemove={(id) => setItems((now) => now.filter((item) => item.id !== id))}
        />
    );
}

/**
 * Images: pictures in their frames, profile images in every size, with initials, status and a group, and the image upload with
 * its states.
 */
export function ImagesSection() {
    const [previews, setPreviews] = useState(STATE_PREVIEWS);

    return (
        <ShowcaseSection
            id="images"
            title="Images"
            description="Every picture holds its shape from the start, says in words when it cannot load, and has text for screen readers. Profile images fall back to initials."
        >
            <div className="grid gap-4 sm:grid-cols-3">
                <Picture src={demoLandscape('forest')} alt="Green hills under a gold sun" ratio="16/9" caption="16 : 9" />
                <Picture src={demoLandscape('gold')} alt="Gold hills under a pale sun" ratio="4/3" caption="4 : 3" />
                <Picture src={demoLandscape('blue')} alt="Blue hills under a gold sun" ratio="1/1" caption="1 : 1" />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <SlowPicture />
                <Picture src="/images/does-not-exist.png" alt="A picture that is not there" ratio="16/9" caption="Cannot load" />
                <Picture src={null} alt="" ratio="16/9" fallbackText="No image yet" caption="No image" />
            </div>
            <div className="flex flex-col gap-4 rounded-xl border bg-card p-5">
                <h3 className="text-h4 font-semibold">Profile images</h3>
                <div className="flex flex-wrap items-end gap-4">
                    {SIZES.map((size) => (
                        <ProfileImage key={size} name="Ama Mensah" src={demoPortrait('#CDD8D0')} size={size} />
                    ))}
                </div>
                <div className="flex flex-wrap items-end gap-4">
                    {PEOPLE.map((name) => (
                        <ProfileImage key={name} name={name} size="lg" />
                    ))}
                    <ProfileImage name="Cannot Load" src="/images/does-not-exist.png" size="lg" />
                </div>
                <div className="flex flex-wrap items-end gap-4">
                    <ProfileImage name="Ama Mensah" size="lg" status={{ label: 'Online', tone: 'online' }} />
                    <ProfileImage name="Kofi Boateng" size="lg" status={{ label: 'Away', tone: 'away' }} />
                    <ProfileImage name="Esi Owusu" size="lg" status={{ label: 'Offline', tone: 'offline' }} />
                    <div role="group" aria-label="Team: six people">
                        <AvatarGroup>
                            {PEOPLE.slice(0, 3).map((name) => (
                                <ProfileImage key={name} name={name} decorative />
                            ))}
                            <AvatarGroupCount>+3</AvatarGroupCount>
                        </AvatarGroup>
                    </div>
                </div>
            </div>
            <LiveUpload />
            <div className="flex flex-col gap-2">
                <h3 className="text-h4 font-semibold">Upload states (previews, not real uploads)</h3>
                <ImageUpload
                    label="Photos"
                    {...RULES}
                    multiple
                    items={previews}
                    onFiles={() => undefined}
                    onRemove={(id) => setPreviews((now) => now.filter((item) => item.id !== id))}
                    onRetry={() => undefined}
                    onCancel={() => undefined}
                />
            </div>
        </ShowcaseSection>
    );
}
