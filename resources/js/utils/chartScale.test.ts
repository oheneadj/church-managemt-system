import { describe, expect, it } from 'vitest';
import { chartScale } from '@/utils/chartScale';

describe('chartScale', () => {
    it('counts in round steps up to a round top at or above the largest value', () => {
        expect(chartScale(156, 4)).toEqual({ max: 200, ticks: [0, 50, 100, 150, 200] });
        expect(chartScale(124, 4)).toEqual({ max: 150, ticks: [0, 50, 100, 150] });
        expect(chartScale(7, 4)).toEqual({ max: 8, ticks: [0, 2, 4, 6, 8] });
    });

    it('keeps a value that is already round as the top of the axis', () => {
        expect(chartScale(100, 4)).toEqual({ max: 100, ticks: [0, 50, 100] });
    });

    it('still gives a small axis for an empty chart', () => {
        const scale = chartScale(0, 4);

        expect(scale.max).toBeGreaterThan(0);
        expect(scale.ticks[0]).toBe(0);
    });
});
