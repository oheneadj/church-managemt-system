import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from 'cn';
import { Loader2Icon } from 'lucide-react';
import { Slot } from 'radix-ui';
import { LOADING_INDICATOR_DELAY_MS } from '@/constants/interaction';
import { useDelayedFlag } from '@/hooks/useDelayedFlag';

const buttonVariants = cva(
    "group/button inline-flex shrink-0 items-center justify-center rounded-lg border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all outline-none select-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground hover:bg-primary/80',
                outline:
                    'border-border bg-background hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:border-input dark:bg-input/30 dark:hover:bg-input/50',
                secondary:
                    'bg-secondary text-secondary-foreground hover:bg-[color-mix(in_oklch,var(--secondary),var(--foreground)_5%)] aria-expanded:bg-secondary aria-expanded:text-secondary-foreground',
                ghost: 'hover:bg-muted hover:text-foreground aria-expanded:bg-muted aria-expanded:text-foreground dark:hover:bg-muted/50',
                destructive:
                    'bg-destructive/10 text-destructive hover:bg-destructive/20 focus-visible:border-destructive/40 focus-visible:ring-destructive/20 dark:bg-destructive/20 dark:hover:bg-destructive/30 dark:focus-visible:ring-destructive/40',
                // The solid danger button is for the final step of a hard-to-undo action; `destructive` above is the quiet, tinted one.
                danger: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            // Heights: phones get the 44px touch target and desktops the denser size (docs/design-system/foundations.md, control sizes).
            size: {
                default:
                    "h-11 gap-2 px-4 md:h-10 has-data-[icon=inline-end]:pr-3 has-data-[icon=inline-start]:pl-3 [&_svg:not([class*='size-'])]:size-5",
                xs: "h-8 gap-1 rounded-md px-2.5 text-xs md:h-7 in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3.5",
                sm: "h-10 gap-1.5 rounded-lg px-3 md:h-9 in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2 [&_svg:not([class*='size-'])]:size-4",
                lg: "h-11 gap-2 px-5 has-data-[icon=inline-end]:pr-4 has-data-[icon=inline-start]:pl-4 [&_svg:not([class*='size-'])]:size-5",
                icon: "size-11 md:size-10 [&_svg:not([class*='size-'])]:size-5",
                'icon-xs': "size-8 rounded-md md:size-7 in-data-[slot=button-group]:rounded-lg [&_svg:not([class*='size-'])]:size-3.5",
                'icon-sm': 'size-10 rounded-lg md:size-9 in-data-[slot=button-group]:rounded-lg',
                'icon-lg': 'size-11',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

interface ButtonProps extends React.ComponentProps<'button'>, VariantProps<typeof buttonVariants> {
    asChild?: boolean;
    /** True while the action this button starts is running. It blocks a second press at once and, if the work is slow, shows a spinner. Not used with `asChild`. */
    loading?: boolean;
    /** What the label says while loading, for example "Saving…". Leave out to keep the label. */
    loadingLabel?: string;
}

/**
 * The one button (docs/design-system/interactions.md, section 5). While `loading` it ignores presses (so a form cannot be sent
 * twice), says it is busy to assistive technology, keeps the keyboard focus, and keeps the width it had so nothing moves. The
 * spinner and the loading label appear only if the work takes longer than a moment, so a quick save does not flash.
 */
function Button({
    className,
    variant = 'default',
    size = 'default',
    asChild = false,
    loading = false,
    loadingLabel,
    onClick,
    children,
    ref,
    ...props
}: ButtonProps) {
    const element = React.useRef<HTMLButtonElement | null>(null);
    const restingWidth = React.useRef<number | null>(null);
    const busy = loading && !asChild;
    const showIndicator = useDelayedFlag(busy, LOADING_INDICATOR_DELAY_MS);

    // The width the button has at rest is kept, and held as a minimum while the indicator shows, so the layout does not shift.
    React.useLayoutEffect(() => {
        const node = element.current;

        if (node === null) {
            return;
        }

        if (showIndicator) {
            node.style.minWidth = restingWidth.current === null ? '' : `${restingWidth.current}px`;
        } else {
            node.style.minWidth = '';
            restingWidth.current = node.getBoundingClientRect().width;
        }
    });

    const setRefs = (node: HTMLButtonElement | null) => {
        element.current = node;

        if (typeof ref === 'function') {
            ref(node);
        } else if (ref) {
            ref.current = node;
        }
    };

    const handleClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        if (busy) {
            event.preventDefault();

            return;
        }

        onClick?.(event);
    };

    const classes = cn(buttonVariants({ variant, size, className }), busy && 'cursor-progress');

    if (asChild) {
        return (
            <Slot.Root data-slot="button" data-variant={variant} data-size={size} className={classes} {...props}>
                {children}
            </Slot.Root>
        );
    }

    return (
        <>
            <button
                ref={setRefs}
                data-slot="button"
                data-variant={variant}
                data-size={size}
                className={classes}
                aria-busy={busy || undefined}
                aria-disabled={busy || undefined}
                onClick={handleClick}
                {...props}
            >
                {showIndicator ? (
                    <>
                        <Loader2Icon
                            aria-hidden="true"
                            className="animate-spin"
                            style={{ width: 'var(--size-spinner-md)', height: 'var(--size-spinner-md)' }}
                        />
                        {loadingLabel ?? children}
                    </>
                ) : (
                    children
                )}
            </button>
            {/* Said once, outside the button so it does not become part of the button's name (which would read "Saving… Saving…"). */}
            {showIndicator && (
                <span role="status" className="sr-only">
                    {loadingLabel ?? 'Working'}
                </span>
            )}
        </>
    );
}

export { Button, buttonVariants };
