import { ImageOffIcon } from 'lucide-react';
import { useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from 'cn';

/** The shapes a picture may take. Each reserves its space before the image arrives, so nothing jumps. */
export const PICTURE_RATIOS = { '1/1': '1 / 1', '4/3': '4 / 3', '3/2': '3 / 2', '16/9': '16 / 9' } as const;

interface PictureProps {
    /** The address of the image. Leave out (or null) when there is none: the frame then says so. */
    src?: string | null;
    /** What the image shows, in words. Use an empty string only for a purely decorative image whose meaning the text beside it already gives. */
    alt: string;
    ratio?: keyof typeof PICTURE_RATIOS;
    /** `cover` fills the frame and crops; `contain` shows all of it with space around. An image is never stretched. */
    fit?: 'cover' | 'contain';
    /** A line under the picture, kept with it as a caption. */
    caption?: string;
    /** True for an image at the top of the page that people see first: it loads at once instead of when it nears the screen. */
    priority?: boolean;
    /** What the frame says when the image is missing or cannot load. */
    fallbackText?: string;
    className?: string;
}

interface FrameProps extends Omit<PictureProps, 'caption' | 'className'> {
    ratio: keyof typeof PICTURE_RATIOS;
    fit: 'cover' | 'contain';
    fallbackText: string;
}

type Phase = 'loading' | 'loaded' | 'failed';

/** The frame itself. It is keyed by the image address, so a new address starts again at "loading". */
function Frame({ src, alt, ratio, fit, priority = false, fallbackText }: FrameProps) {
    const [phase, setPhase] = useState<Phase>(src ? 'loading' : 'failed');

    return (
        <div
            data-slot="picture"
            data-phase={phase}
            className="relative w-full overflow-hidden rounded-xl border bg-muted"
            style={{ aspectRatio: PICTURE_RATIOS[ratio] }}
        >
            {src && phase !== 'failed' && (
                <img
                    src={src}
                    alt={alt}
                    loading={priority ? 'eager' : 'lazy'}
                    decoding="async"
                    onLoad={() => setPhase('loaded')}
                    onError={() => setPhase('failed')}
                    className={cn(
                        'absolute inset-0 size-full transition-opacity',
                        fit === 'cover' ? 'object-cover' : 'object-contain',
                        phase === 'loaded' ? 'opacity-100' : 'opacity-0',
                    )}
                />
            )}
            {phase === 'loading' && <Skeleton data-slot="picture-skeleton" className="absolute inset-0 rounded-none" />}
            {phase === 'failed' && (
                <div
                    role="img"
                    aria-label={fallbackText}
                    className="absolute inset-0 flex flex-col items-center justify-center gap-2 p-4 text-center text-body-sm text-muted-foreground"
                >
                    <ImageOffIcon aria-hidden="true" className="size-6" />
                    <span>{fallbackText}</span>
                </div>
            )}
        </div>
    );
}

/**
 * An image in a rounded, bordered frame (docs/design-system/foundations.md, 5b). The frame holds its shape from the start, a skeleton
 * shows until the image is ready, the image fades in, it loads lazily unless it is `priority`, and when it is missing or fails the
 * frame says so in words with an icon instead of showing a broken image. Always give `alt` text.
 */
export function Picture({
    src,
    alt,
    ratio = '16/9',
    fit = 'cover',
    caption,
    priority = false,
    fallbackText = 'Image unavailable',
    className,
}: PictureProps) {
    const frame = <Frame key={src ?? 'none'} src={src} alt={alt} ratio={ratio} fit={fit} priority={priority} fallbackText={fallbackText} />;

    return caption ? (
        <figure className={cn('flex flex-col gap-2', className)}>
            {frame}
            <figcaption className="text-body-sm text-muted-foreground">{caption}</figcaption>
        </figure>
    ) : (
        <div className={className}>{frame}</div>
    );
}
