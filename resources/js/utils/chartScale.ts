/** The round step sizes a value axis counts in, as multiples of a power of ten (1, 2, 5, 10, 20, 50 ...). */
const NICE_STEPS = [1, 2, 5, 10];

export interface ChartScale {
    /** The top of the axis: a round number at or above the largest value. */
    max: number;
    /** The labelled values on the axis, from 0 up to `max`. */
    ticks: number[];
}

/**
 * A value axis that starts at 0 and counts in round steps (0, 50, 100, 150) up to a round top at or above the largest value.
 * `aimForTicks` is how many steps to aim for; the real number can be fewer. An empty chart still gets a small axis, so
 * there is never a division by zero.
 */
export function chartScale(largest: number, aimForTicks: number): ChartScale {
    const target = Math.max(largest, 1);
    const rough = target / aimForTicks;
    const power = 10 ** Math.floor(Math.log10(rough));
    const step = (NICE_STEPS.find((nice) => nice * power >= rough) ?? 10) * power;
    const count = Math.ceil(target / step);

    return { max: step * count, ticks: Array.from({ length: count + 1 }, (_, index) => index * step) };
}

export interface DivergingScale extends ChartScale {
    /** The bottom of the axis: 0, or a round negative number when something is drawn below the baseline. */
    min: number;
}

/**
 * A value axis for columns that go up from 0 and may also go down from it (new members and lost ones). One step size is shared
 * by both sides so the grid lines up, and each side is only as tall as its largest column needs. With nothing below the
 * baseline it is the same axis `chartScale` gives, so a chart that never goes down is unchanged.
 */
export function divergingScale(up: number, down: number, aimForTicks: number): DivergingScale {
    const step = chartScale(Math.max(up, down), aimForTicks).ticks[1] ?? 1;
    const upSteps = Math.max(Math.ceil(up / step), 1);
    const downSteps = Math.ceil(down / step);

    return {
        min: 0 - downSteps * step,
        max: upSteps * step,
        ticks: Array.from({ length: downSteps + upSteps + 1 }, (_, index) => (index - downSteps) * step),
    };
}
