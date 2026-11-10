import { CheckIcon } from 'lucide-react';
import { cn } from 'cn';

interface StepIndicatorProps {
    titles: readonly string[];
    /** Zero-based index of the current step. */
    current: number;
}

/**
 * Progress through a form that has steps: "Step 2 of 3", then a bar and the name of every step. A step already done is filled and
 * ticked, the current one is filled and bold, and the ones to come are quiet; the current step is also marked for screen readers
 * (`aria-current="step"`) and a done step says so in hidden words, so no step is told by colour alone.
 */
export function StepIndicator({ titles, current }: StepIndicatorProps) {
    return (
        <div className="flex flex-col gap-3">
            <p className="text-body-sm text-muted-foreground">
                Step {current + 1} of {titles.length}
            </p>
            <ol aria-label="Steps" className="grid gap-3" style={{ gridTemplateColumns: `repeat(${titles.length}, minmax(0, 1fr))` }}>
                {titles.map((title, index) => {
                    const done = index < current;

                    return (
                        <li key={title} aria-current={index === current ? 'step' : undefined} className="flex flex-col gap-2">
                            <span aria-hidden="true" className={cn('h-1.5 rounded-full', index <= current ? 'bg-primary' : 'bg-muted')} />
                            <span
                                className={cn(
                                    'flex items-start gap-1 text-body-sm',
                                    index === current ? 'font-semibold' : 'text-muted-foreground',
                                )}
                            >
                                {done && <CheckIcon aria-hidden="true" className="mt-0.5 size-4 shrink-0 text-feedback-success" />}
                                <span>
                                    {index + 1}. {title}
                                    {done && <span className="sr-only"> (done)</span>}
                                </span>
                            </span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
