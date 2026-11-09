'use client';

import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { CircleCheckIcon, InfoIcon, TriangleAlertIcon, OctagonXIcon, Loader2Icon } from 'lucide-react';

/**
 * The three colours Sonner reads for each kind of toast: a deep solid background, light text, and a border a little lighter than the
 * background so the toast keeps its edge on a dark page.
 */
function kindColors(kind: 'success' | 'info' | 'warning' | 'error') {
    return {
        [`--${kind}-bg`]: `var(--feedback-${kind}-solid)`,
        [`--${kind}-border`]: `color-mix(in oklch, var(--feedback-${kind}-solid), white 22%)`,
        [`--${kind}-text`]: 'var(--feedback-solid-foreground)',
    };
}

/**
 * The toast area. Each kind (success, information, warning, error) is a deep, solid colour with light text, always with its icon as
 * well, so the kind never depends on colour alone. A toast in progress and a plain one use the neutral surface.
 */
const Toaster = ({ ...props }: ToasterProps) => {
    return (
        <Sonner
            richColors
            theme="system"
            className="toaster group"
            icons={{
                success: <CircleCheckIcon className="size-4" />,
                info: <InfoIcon className="size-4" />,
                warning: <TriangleAlertIcon className="size-4" />,
                error: <OctagonXIcon className="size-4" />,
                loading: <Loader2Icon className="size-4 animate-spin" />,
            }}
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    '--border-radius': 'var(--radius-xl)',
                    ...kindColors('success'),
                    ...kindColors('info'),
                    ...kindColors('warning'),
                    ...kindColors('error'),
                } as React.CSSProperties
            }
            toastOptions={{
                classNames: {
                    toast: 'cn-toast',
                    // The action ("Try again") sits on the deep colour, so it is a light outline rather than Sonner's own black pill.
                    actionButton: '!bg-white/20 !text-white hover:!bg-white/30',
                    closeButton: '!border-white/30',
                },
            }}
            {...props}
        />
    );
};

export { Toaster };
