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
