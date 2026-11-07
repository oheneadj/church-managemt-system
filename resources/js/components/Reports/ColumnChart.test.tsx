import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ColumnChart, type ChartSeries, type ColumnPoint } from '@/components/Reports/ColumnChart';

const PEOPLE: readonly ChartSeries[] = [{ key: 'people', name: 'People', color: 'red' }];

/** A column of one series, the way attendance draws it. */
function point(id: string, value: number, extra: Partial<ColumnPoint> = {}): ColumnPoint {
    return {
        id,
        label: `L-${id}`,
        title: `Week ${id}`,
        totalText: `${value} people`,
        peakText: String(value),
        parts: [{ seriesKey: 'people', value, text: String(value) }],
        ...extra,
    };
}

const points = [point('a', 50), point('b', 124, { detail: '2 services · 3 first-timers' }), point('c', 0)];

describe('ColumnChart', () => {
    it('gives every column an accessible name with its total, so the chart is readable without seeing it', () => {
        render(<ColumnChart points={points} series={PEOPLE} />);

        expect(screen.getByRole('button', { name: 'Week a: 50 people' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Week b: 124 people' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Week c: 0 people' })).toBeInTheDocument();
    });

    it('shows the total, the title and the detail in a tooltip on hover, and takes it away again', async () => {
        const user = userEvent.setup();
        render(<ColumnChart points={points} series={PEOPLE} />);

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
        render(<ColumnChart points={[point('tall', 150), point('short', 10)]} series={PEOPLE} />);

        await user.hover(screen.getByRole('button', { name: 'Week tall: 150 people' }));
        expect(screen.getByRole('tooltip').style.top).toBe('0px');

        await user.hover(screen.getByRole('button', { name: 'Week short: 10 people' }));
        expect(screen.getByRole('tooltip').style.bottom).toContain('calc(');
        expect(screen.getByRole('tooltip').style.top).toBe('');
    });

    it('slides the tooltip with the column, from the left edge to the right edge, so it can never run out of the box', async () => {
        const user = userEvent.setup();
        render(<ColumnChart points={[point('first', 10), point('middle', 10), point('last', 10)]} series={PEOPLE} />);

        await user.hover(screen.getByRole('button', { name: 'Week first: 10 people' }));
        expect(screen.getByRole('tooltip').style.left).toBe('16.666666666666664%');
        expect(screen.getByRole('tooltip').style.transform).toBe('translateX(-16.666666666666664%)');

        await user.hover(screen.getByRole('button', { name: 'Week last: 10 people' }));
        expect(screen.getByRole('tooltip').style.transform).toBe('translateX(-83.33333333333334%)');
    });

    it('shows the same tooltip when a column is reached with the keyboard', async () => {
        const user = userEvent.setup();
        render(<ColumnChart points={points} series={PEOPLE} />);

        await user.tab();
        expect(screen.getByRole('tooltip')).toHaveTextContent('50 people');

        await user.tab();
        expect(screen.getByRole('tooltip')).toHaveTextContent('124 people');
    });

    it('prints the value on the highest column only, not on every column', () => {
        render(<ColumnChart points={points} series={PEOPLE} />);

        const printed = screen.getAllByText('124').filter((node) => node.closest('[aria-hidden="true"]') !== null);
        expect(printed).toHaveLength(1);
        expect(screen.queryAllByText('50').filter((node) => node.closest('button') !== null)).toHaveLength(0);
    });

    it('prints nothing above the highest column when its text is too wide to fit, leaving it to the tooltip', () => {
        render(<ColumnChart points={[point('a', 90, { peakText: undefined })]} series={PEOPLE} />);

        expect(screen.queryAllByText('90').filter((node) => node.closest('button') !== null)).toHaveLength(0);
    });

    it('prints no value when nothing was recorded', () => {
        render(<ColumnChart points={[point('a', 0), point('b', 0)]} series={PEOPLE} />);

        expect(screen.queryAllByText('0').filter((node) => node.closest('button') !== null)).toHaveLength(0);
    });

    it('labels the newest column and only a handful along the bottom, not every one', () => {
        const many = Array.from({ length: 52 }, (_, index) => point(String(index), index + 1));
        render(<ColumnChart points={many} series={PEOPLE} />);

        expect(screen.getAllByText(/^L-/).length).toBeLessThanOrEqual(7);
        expect(screen.getByText('L-51')).toBeInTheDocument();
    });

    it('dims the chart while newer figures load, without removing it', () => {
        const { container } = render(<ColumnChart points={points} series={PEOPLE} refreshing />);

        expect(container.querySelector('.opacity-50')).not.toBeNull();
        expect(screen.getAllByRole('button')).toHaveLength(3);
    });

    it('counts the axis in round steps from 0 and says what the numbers are in', () => {
        render(<ColumnChart points={points} series={PEOPLE} axisCaption="GHS" />);

        for (const tick of ['0', '50', '100', '150']) {
            expect(screen.getAllByText(tick).length).toBeGreaterThan(0);
        }
        expect(screen.getByText('GHS')).toBeInTheDocument();
    });

    it('has no legend for one series, because the title already says what it is', () => {
        render(<ColumnChart points={points} series={PEOPLE} />);

        expect(screen.queryByRole('list', { name: 'Legend' })).not.toBeInTheDocument();
    });
});

describe('ColumnChart with several series', () => {
    const FUNDS: readonly ChartSeries[] = [
        { key: 'tithe', name: 'Tithe', color: 'blue' },
        { key: 'building', name: 'Building Fund', color: 'orange' },
    ];
    const month: ColumnPoint = {
        id: '2026-10',
        label: 'Oct 26',
        title: 'October 2026',
        totalText: 'GHS 15,450.00',
        parts: [
            { seriesKey: 'tithe', value: 8000, text: 'GHS 8,000.00' },
            { seriesKey: 'building', value: 7450, text: 'GHS 7,450.00' },
        ],
    };

    it('always shows a legend naming every series', () => {
        render(<ColumnChart points={[month]} series={FUNDS} />);

        const legend = screen.getByRole('list', { name: 'Legend' });
        expect(within(legend).getByText('Tithe')).toBeInTheDocument();
        expect(within(legend).getByText('Building Fund')).toBeInTheDocument();
    });

    it('stacks one block for each series in the order of the series, a gap apart', () => {
        const { container } = render(<ColumnChart points={[month]} series={FUNDS} />);

        const blocks = container.querySelectorAll<HTMLElement>('button span[style*="background-color"]');
        expect(Array.from(blocks).map((block) => block.style.backgroundColor)).toEqual(['blue', 'orange']);
        expect(blocks[0]?.className).not.toContain('border-t-2');
        expect(blocks[1]?.className).toContain('border-t-2');
    });

    it('lists every series in the tooltip, each with its own figure', async () => {
        const user = userEvent.setup();
        render(<ColumnChart points={[month]} series={FUNDS} />);

        await user.hover(screen.getByRole('button', { name: 'October 2026: GHS 15,450.00' }));

        const tooltip = screen.getByRole('tooltip');
        expect(tooltip).toHaveTextContent('GHS 15,450.00');
        expect(tooltip).toHaveTextContent('GHS 8,000.00Tithe');
        expect(tooltip).toHaveTextContent('GHS 7,450.00Building Fund');
    });

    it('leaves a series that gave nothing out of the stack and the tooltip', async () => {
        const user = userEvent.setup();
        const onlyTithe: ColumnPoint = {
            ...month,
            parts: [{ seriesKey: 'tithe', value: 8000, text: 'GHS 8,000.00' }],
            totalText: 'GHS 8,000.00',
        };
        const { container } = render(<ColumnChart points={[onlyTithe]} series={FUNDS} />);

        expect(container.querySelectorAll('button span[style*="background-color"]')).toHaveLength(1);
        await user.hover(screen.getByRole('button'));
        expect(screen.getByRole('tooltip')).not.toHaveTextContent('Building Fund');
    });
});
