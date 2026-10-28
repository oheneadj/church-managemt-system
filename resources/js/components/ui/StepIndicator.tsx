interface StepIndicatorProps {
    titles: readonly string[];
    /** Zero-based index of the current step. */
    current: number;
}

/**
 * "Step 2 of 3" with the names of all steps. The current one is marked for screen readers as well as by weight.
 */
export function StepIndicator({ titles, current }: StepIndicatorProps) {
    return (
        <div className="flex flex-col gap-2">
            <p className="text-sm text-muted-foreground">
                Step {current + 1} of {titles.length}
            </p>
            <ol aria-label="Steps" className="flex gap-2 text-sm">
                {titles.map((title, index) => (
                    <li
                        key={title}
                        aria-current={index === current ? 'step' : undefined}
                        className={index === current ? 'font-semibold' : 'text-muted-foreground'}
                    >
                        {index + 1}. {title}
                    </li>
                ))}
            </ol>
        </div>
    );
}
