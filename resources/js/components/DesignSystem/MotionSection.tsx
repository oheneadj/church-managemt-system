import type { CSSProperties } from 'react';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';

const DURATIONS = [
    ['Instant', '--motion-duration-instant', 'Pressed feedback'],
    ['Micro', '--motion-duration-micro', 'Hover, focus, checkbox, switch'],
    ['Component', '--motion-duration-component', 'Menu, popover, accordion'],
    ['Overlay', '--motion-duration-overlay', 'Dialog, drawer, toast'],
    ['Content', '--motion-duration-content', 'Larger content change'],
] as const;

/**
 * Motion: each duration token as a bar that fills when hovered or focused, so the pace of every layer can be felt. With reduced
 * motion on, the bars jump at once, which is the intended behaviour.
 */
export function MotionSection() {
    return (
        <ShowcaseSection
            id="motion"
            title="Motion"
            description="Hover or focus a row to see its duration. Turn on reduced motion in your system settings and the movement stops while the state still changes."
        >
            <ul className="flex flex-col gap-2">
                {DURATIONS.map(([name, token, use]) => (
                    <li key={token}>
                        <button
                            type="button"
                            className="group flex w-full max-w-xl flex-col gap-1.5 rounded-lg p-2 text-left outline-none hover:bg-surface-hover focus-visible:ring-3 focus-visible:ring-ring"
                        >
                            <span className="flex justify-between text-sm">
                                <span className="font-medium">{name}</span>
                                <span className="text-muted-foreground">{use}</span>
                            </span>
                            <span className="h-2 overflow-hidden rounded-full bg-muted">
                                <span
                                    className="block h-full w-0 rounded-full bg-primary transition-[width] duration-(--bar-duration) ease-(--motion-ease-standard) group-hover:w-full group-focus-visible:w-full"
                                    style={{ '--bar-duration': `var(${token})` } as CSSProperties}
                                />
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </ShowcaseSection>
    );
}
