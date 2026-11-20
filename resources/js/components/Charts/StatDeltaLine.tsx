import { ArrowDownRightIcon, ArrowUpRightIcon, MinusIcon } from 'lucide-react';
import { cn } from 'cn';

export interface StatDelta {
    /** The change as signed text, already formatted: "+12.4%", "-3", "No change". */
    text: string;
    direction: 'up' | 'down' | 'flat';
    /** Whether going up is a good thing (more items: yes; open issues: no). It decides the colour, not the arrow. */
    upIsGood?: boolean;
    /** What it is compared with, in words: "vs last month". */
    against: string;
}

interface StatDeltaLineProps {
    delta: StatDelta;
    /** True on a filled card: the text then takes the card's own text colour (green and red do not read on a deep fill) and the arrow and words carry the change. */
    onFilled?: boolean;
}

const ICONS = { up: ArrowUpRightIcon, down: ArrowDownRightIcon, flat: MinusIcon } as const;

/**
 * The change on a stat card: an arrow plus signed words against a named period, never colour alone. On a plain card the colour is
 * what the change means (an increase in something good is green, an increase in something bad is red; flat is quiet). Screen readers
 * hear the direction in words and skip the arrow.
 */
export function StatDeltaLine({ delta, onFilled = false }: StatDeltaLineProps) {
    const good = delta.direction === 'flat' ? null : (delta.direction === 'up') === (delta.upIsGood ?? true);
    const Icon = ICONS[delta.direction];
    const tone = onFilled ? '' : good === null ? 'text-muted-foreground' : good ? 'text-feedback-success' : 'text-feedback-error';

    return (
        <p data-slot="stat-delta" data-good={good ?? undefined} className={cn('flex items-center gap-1 text-body-sm', tone)}>
            <Icon aria-hidden="true" className="size-4 shrink-0" />
            <span>
                {delta.direction !== 'flat' && <span className="sr-only">{delta.direction === 'up' ? 'Up ' : 'Down '}</span>}
                <span className="font-medium">{delta.text}</span>{' '}
                <span className={onFilled ? 'opacity-80' : 'text-muted-foreground'}>{delta.against}</span>
            </span>
        </p>
    );
}
