interface MiniColumnsProps {
    /** The values in time order; the last one is the current period. */
    values: readonly number[];
    /** What the columns show, in words, for assistive technology. */
    label: string;
}

const HEIGHT = 40;
const BAR = 8;
const GAP = 4;

/**
 * A small column chart beside a number: thin columns on one baseline with 4px rounded tops, earlier periods in the quiet
 * de-emphasis colour and the current one in the first chart colour. It carries no numbers; the stat it sits in says them.
 */
export function MiniColumns({ values, label }: MiniColumnsProps) {
    const top = Math.max(...values, 1);
    const width = values.length * BAR + (values.length - 1) * GAP;

    return (
        <svg role="img" aria-label={label} width={width} height={HEIGHT} viewBox={`0 0 ${width} ${HEIGHT}`} className="shrink-0">
            {values.map((value, index) => {
                const height = Math.max((value / top) * HEIGHT, 4);

                return (
                    <rect
                        key={index}
                        x={index * (BAR + GAP)}
                        y={HEIGHT - height}
                        width={BAR}
                        height={height}
                        rx={4}
                        fill={index === values.length - 1 ? 'var(--viz-series-1)' : 'var(--viz-other)'}
                    />
                );
            })}
        </svg>
    );
}
