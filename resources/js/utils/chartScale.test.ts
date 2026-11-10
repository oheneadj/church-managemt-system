import { describe, expect, it } from 'vitest';
import { chartScale, divergingScale } from '@/utils/chartScale';

describe('chartScale', () => {
    it('counts in round steps up to a round top at or above the largest value', () => {
        expect(chartScale(156, 4)).toEqual({ max: 200, ticks: [0, 50, 100, 150, 200] });
        expect(chartScale(124, 4)).toEqual({ max: 150, ticks: [0, 50, 100, 150] });
        expect(chartScale(7, 4)).toEqual({ max: 8, ticks: [0, 2, 4, 6, 8] });
    });

    it('keeps a value that is already round as the top of the axis', () => {
        expect(chartScale(100, 4)).toEqual({ max: 100, ticks: [0, 50, 100] });
    });

    it('never counts in fractions: a chart of one or two people has a whole-number axis', () => {
        expect(chartScale(1, 4)).toEqual({ max: 1, ticks: [0, 1] });
        expect(chartScale(2, 4)).toEqual({ max: 2, ticks: [0, 1, 2] });
        expect(chartScale(3, 4)).toEqual({ max: 3, ticks: [0, 1, 2, 3] });
        expect(chartScale(0, 4).ticks).toEqual([0, 1]);
    });

    it('still gives a small axis for an empty chart', () => {
        const scale = chartScale(0, 4);

        expect(scale.max).toBeGreaterThan(0);
        expect(scale.ticks[0]).toBe(0);
    });
});

describe('divergingScale', () => {
    it('is the same axis as chartScale when nothing goes below the baseline', () => {
        expect(divergingScale(156, 0, 4)).toEqual({ min: 0, max: 200, ticks: [0, 50, 100, 150, 200] });
        expect(divergingScale(7, 0, 4)).toEqual({ min: 0, max: 8, ticks: [0, 2, 4, 6, 8] });
    });

    it('shares one step above and below, each side only as tall as it needs', () => {
        expect(divergingScale(8, 3, 4)).toEqual({ min: -4, max: 8, ticks: [-4, -2, 0, 2, 4, 6, 8] });
    });

    it('still has room above the baseline when everything goes down, and never divides by zero when empty', () => {
        const down = divergingScale(0, 5, 4);
        expect(down.max).toBeGreaterThan(0);
        expect(down.min).toBeLessThanOrEqual(-5);

        const empty = divergingScale(0, 0, 4);
        expect(empty.max - empty.min).toBeGreaterThan(0);
    });
});
