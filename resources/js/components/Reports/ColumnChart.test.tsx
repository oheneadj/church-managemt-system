import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ColumnChart, type ColumnPoint } from '@/components/Reports/ColumnChart';

function point(id: string, value: number, extra: Partial<ColumnPoint> = {}): ColumnPoint {
    return { id, label: `L-${id}`, value, title: `Week ${id}`, ...extra };
}

const points = [point('a', 50), point('b', 124, { detail: '2 services · 3 first-timers' }), point('c', 0)];

describe('ColumnChart', () => {
    it('gives every column an accessible name with its value, so the chart is readable without seeing it', () => {
        render(<ColumnChart points={points} unit="people" />);

        expect(screen.getByRole('button', { name: 'Week a: 50 people' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Week b: 124 people' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Week c: 0 people' })).toBeInTheDocument();
    });

    it('shows the value, the title and the detail in a tooltip on hover, and takes it away again', async () => {
        const user = userEvent.setup();
        render(<ColumnChart points={points} unit="people" />);

        await user.hover(screen.getByRole('button', { name: 'Week b: 124 people' }));

        const tooltip = screen.getByRole('tooltip');
        expect(tooltip).toHaveTextContent('124 people');
        expect(tooltip).toHaveTextContent('Week b');
        expect(tooltip).toHaveTextContent('2 services · 3 first-timers');

        await user.unhover(screen.getByRole('button', { name: 'Week b: 124 people' }));
        expect(screen.queryByRole('tooltip')).not.toBeInTheDocument();
    });

    it('puts the tooltip at the top of the chart for a tall column and above a short one, so it never runs out of the box', async () => {
        const user = userEvent.setup();
        render(<ColumnChart points={[point('tall', 150), point('short', 10)]} unit="people" />);

        await user.hover(screen.getByRole('button', { name: 'Week tall: 150 people' }));
        expect(screen.getByRole('tooltip').style.top).toBe('0px');

        await user.hover(screen.getByRole('button', { name: 'Week short: 10 people' }));
        expect(screen.getByRole('tooltip').style.bottom).toContain('calc(');
        expect(screen.getByRole('tooltip').style.top).toBe('');
    });

    it('shows the same tooltip when a column is reached with the keyboard', async () => {
        const user = userEvent.setup();
        render(<ColumnChart points={points} unit="people" />);

        await user.tab();
        expect(screen.getByRole('tooltip')).toHaveTextContent('50 people');

        await user.tab();
        expect(screen.getByRole('tooltip')).toHaveTextContent('124 people');
    });

    it('prints the value on the highest column only, not on every column', () => {
        render(<ColumnChart points={points} unit="people" />);

        // The accessible names hold the values too, so look for the printed text on the marks.
        const printed = screen.getAllByText('124').filter((node) => node.closest('[aria-hidden="true"]') !== null);
        expect(printed).toHaveLength(1);
        expect(screen.queryAllByText('50').filter((node) => node.closest('button') !== null)).toHaveLength(0);
    });

    it('prints no value when nothing was recorded', () => {
        render(<ColumnChart points={[point('a', 0), point('b', 0)]} unit="people" />);

        expect(screen.queryAllByText('0').filter((node) => node.closest('button') !== null)).toHaveLength(0);
    });

    it('labels the newest column and about six along the bottom, not every one', () => {
        const many = Array.from({ length: 52 }, (_, index) => point(String(index), index + 1));
        render(<ColumnChart points={many} unit="people" />);

        const labels = screen.getAllByText(/^L-/);
        expect(labels.length).toBeLessThanOrEqual(7);
        expect(screen.getByText('L-51')).toBeInTheDocument();
    });

    it('dims the chart while newer figures load, without removing it', () => {
        const { container } = render(<ColumnChart points={points} unit="people" refreshing />);

        expect(container.querySelector('.opacity-50')).not.toBeNull();
        expect(screen.getAllByRole('button')).toHaveLength(3);
    });

    it('counts the axis in round steps from 0', () => {
        render(<ColumnChart points={points} unit="people" />);

        for (const tick of ['0', '50', '100', '150']) {
            expect(screen.getAllByText(tick).length).toBeGreaterThan(0);
        }
    });
});
