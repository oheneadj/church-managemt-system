import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initialsOf } from '@/utils/initialsOf';
import { cn } from 'cn';

/** Sizes in pixels: 24, 32, 40, 48, 64, 96. Each carries its own initials size. */
const SIZES = {
    xs: 'size-6 text-[0.625rem]',
    sm: 'size-8 text-caption',
    md: 'size-10 text-body-sm',
    lg: 'size-12 text-body',
    xl: 'size-16 text-h4',
    '2xl': 'size-24 text-h2',
} as const;

export type ProfileImageSize = keyof typeof SIZES;

interface ProfileImageProps {
    /** The person's name. It gives the initials, the colour of the fallback and the text alternative. */
    name: string;
    /** The photo. Leave out (or null) for none: the initials are shown. A photo that cannot load falls back to the initials too. */
    src?: string | null;
    size?: ProfileImageSize;
    /** True when the name is written beside the image, so a screen reader is not told it twice. */
    decorative?: boolean;
    /** A small dot for presence. The state is also said in words for screen readers, never by colour alone. */
    status?: { label: string; tone: 'online' | 'away' | 'offline' };
    className?: string;
}

/** How many tints the fallback may take: the five chart colours, softened. */
const TINTS = 5;

/** The same name always gets the same tint, so a person is recognisable across the app. */
function tintOf(name: string): number {
    return ([...name].reduce((sum, letter) => sum + letter.charCodeAt(0), 0) % TINTS) + 1;
}

const STATUS_COLOURS = {
    online: 'var(--feedback-success-solid)',
    away: 'var(--feedback-warning-solid)',
    offline: 'var(--viz-other)',
} as const;

/**
 * A person's photo as a circle, or their initials on a soft tint when there is none or it will not load (docs/design-system/
 * foundations.md, 5b). It is one picture with the person's name as its text alternative; beside a written name, mark it
 * `decorative`. A fine ring in the border colour keeps a pale photo from melting into the page.
 */
export function ProfileImage({ name, src, size = 'md', decorative = false, status, className }: ProfileImageProps) {
    const tint = tintOf(name);

    return (
        <span className="relative inline-flex shrink-0" {...(decorative ? { 'aria-hidden': true } : { role: 'img', 'aria-label': name })}>
            <Avatar data-slot="profile-image" className={cn(SIZES[size], className)}>
                {src && <AvatarImage key={src} src={src} alt="" />}
                <AvatarFallback
                    aria-hidden="true"
                    className="font-medium text-foreground"
                    style={{ background: `color-mix(in oklch, var(--viz-series-${tint}) 22%, var(--card))` }}
                >
                    {initialsOf(name)}
                </AvatarFallback>
            </Avatar>
            {status && (
                <span
                    data-slot="profile-status"
                    data-tone={status.tone}
                    className="absolute right-0 bottom-0 size-3 rounded-full ring-2 ring-card"
                    style={{ background: STATUS_COLOURS[status.tone] }}
                >
                    <span className="sr-only">{status.label}</span>
                </span>
            )}
        </span>
    );
}
