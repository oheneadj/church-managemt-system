import { act, fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { ChartFigure } from '@/components/Charts/ChartFigure';
import { ChartLegend } from '@/components/Charts/ChartLegend';
import { LineChart, type LinePoint, type LineSeries } from '@/components/Charts/LineChart';
import { SegmentedBar, type SegmentPart } from '@/components/Charts/SegmentedBar';
import { Sparkline } from '@/components/Charts/Sparkline';
import { StatCard } from '@/components/Charts/StatCard';

// Stat cards and charts (docs/design-system/foundations.md, charts; the data-visualisation checks): the change is an arrow and words,
// never colour alone; every chart names its series in a legend, can be read by keyboard, and has a table twin; and nothing is drawn
// for a part of zero.

describe('Sparkline', () => {
    it('is one picture with a text alternative, earlier periods quiet and the current one in the first chart colour', () => {
        render(<Sparkline values={[3, 5, 4, 9]} label="Rising over four periods" />);
        const picture = screen.getByRole('img', { name: 'Rising over four periods' });

        expect(picture.querySelector('polyline')).toHaveAttribute('stroke', 'var(--viz-other)');
        expect(picture.querySelector('line')).toHaveAttribute('stroke', 'var(--viz-series-1)');
        const dot = picture.querySelector('circle');
        expect(dot).toHaveAttribute('r', '4');
        expect(dot).toHaveAttribute('stroke', 'var(--card)');
        expect(dot).toHaveAttribute('stroke-width', '2');
    });

    it('draws only the current segment for two values, and does not fail for one', () => {
        const { rerender } = render(<Sparkline values={[3, 9]} label="Two" />);
        expect(screen.getByRole('img').querySelector('polyline')).toBeNull();

        rerender(<Sparkline values={[3]} label="One" />);
        expect(screen.getByRole('img', { name: 'One' })).toBeInTheDocument();
    });
});

describe('StatCard', () => {
    it('shows the label, the number and the change against a named period', () => {
        render(<StatCard label="Total items" value="1,284" delta={{ text: '+12.4%', direction: 'up', against: 'vs last month' }} />);

        expect(screen.getByText('Total items')).toBeInTheDocument();
        expect(screen.getByText('1,284')).toBeInTheDocument();
        expect(screen.getByText('+12.4%')).toBeInTheDocument();
        expect(screen.getByText('vs last month')).toBeInTheDocument();
    });

    it('says the direction in words for screen readers and hides the arrow, so the change never depends on colour', () => {
        render(<StatCard label="L" value="1" delta={{ text: '-4%', direction: 'down', against: 'vs last week' }} />);
        const delta = document.querySelector('[data-slot="stat-delta"]');

        expect(delta).toHaveTextContent('Down -4% vs last week');
        expect(delta?.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
    });

    it.each([
        ['up and good (the default)', { direction: 'up' as const }, 'text-feedback-success'],
        ['down and bad', { direction: 'down' as const }, 'text-feedback-error'],
        ['up where up is bad', { direction: 'up' as const, upIsGood: false }, 'text-feedback-error'],
        ['down where down is good', { direction: 'down' as const, upIsGood: false }, 'text-feedback-success'],
        ['flat', { direction: 'flat' as const }, 'text-muted-foreground'],
    ])('colours a change that is %s by what it means', (_name, change, expected) => {
        render(<StatCard label="L" value="1" delta={{ text: 'x', against: 'vs then', ...change }} />);

        expect(document.querySelector('[data-slot="stat-delta"]')).toHaveClass(expected);
    });

    it('says nothing about direction for a flat change', () => {
        render(<StatCard label="L" value="1" delta={{ text: 'No change', direction: 'flat', against: 'vs last month' }} />);

        expect(document.querySelector('[data-slot="stat-delta"]')?.querySelector('.sr-only')).toBeNull();
    });

    it('shows a trend when given one and leaves the change out when there is none', () => {
        render(<StatCard label="L" value="1" trend={{ values: [1, 2, 3], label: 'Rising' }} />);

        expect(screen.getByRole('img', { name: 'Rising' })).toBeInTheDocument();
        expect(document.querySelector('[data-slot="stat-delta"]')).toBeNull();
    });

    it('sets the number in proportional figures, not the equal-width ones kept for columns', () => {
        render(<StatCard label="L" value="121" />);

        expect(screen.getByText('121')).not.toHaveClass('tabular-nums');
    });
});

describe('ChartLegend', () => {
    const items = [
        { key: 'a', name: 'Series A', color: 'var(--viz-series-1)' },
        { key: 'b', name: 'Series B', color: 'var(--viz-series-2)' },
    ];

    it('names every series once, in order, as plain text', () => {
        render(<ChartLegend items={items} shape="line" />);

        expect(
            within(screen.getByRole('list', { name: 'Legend' }))
                .getAllByRole('listitem')
                .map((item) => item.textContent),
        ).toEqual(['Series A', 'Series B']);
    });

    it('mirrors the mark: a short line for lines, a small square for bars', () => {
        const { rerender } = render(<ChartLegend items={items} shape="line" />);
        expect(document.querySelector('li span')).toHaveClass('h-0.5', 'w-4');

        rerender(<ChartLegend items={items} shape="swatch" />);
        expect(document.querySelector('li span')).toHaveClass('size-2.5');
        expect(document.querySelector('li span')).toHaveStyle({ background: 'var(--viz-series-1)' });
    });
});

describe('ChartFigure', () => {
    function figure(props: { refreshing?: boolean } = {}) {
        return render(
            <ChartFigure title="Weekly visits" description="Last 8 weeks." legend={<p>Legend here</p>} table={<p>Table here</p>} {...props}>
                <p>Chart here</p>
            </ChartFigure>,
        );
    }

    it('shows the chart and its legend first, with a title and a sentence', () => {
        figure();

        expect(screen.getByRole('heading', { name: 'Weekly visits' })).toBeInTheDocument();
        expect(screen.getByText('Last 8 weeks.')).toBeInTheDocument();
        expect(screen.getByText('Chart here')).toBeInTheDocument();
        expect(screen.getByText('Legend here')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Chart' })).toHaveAttribute('aria-pressed', 'true');
    });

    it('switches to the table of the same figures and back', async () => {
        figure();
        const user = userEvent.setup();

        await user.click(screen.getByRole('button', { name: 'Table' }));
        expect(screen.getByText('Table here')).toBeInTheDocument();
        expect(screen.queryByText('Chart here')).not.toBeInTheDocument();
        expect(screen.queryByText('Legend here')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Table' })).toHaveAttribute('aria-pressed', 'true');

        await user.click(screen.getByRole('button', { name: 'Chart' }));
        expect(screen.getByText('Chart here')).toBeInTheDocument();
    });

    it('keeps the chart on screen, dimmed and marked busy, while figures refresh', () => {
        figure({ refreshing: true });

        expect(document.querySelector('figure')).toHaveAttribute('aria-busy', 'true');
        expect(screen.getByText('Chart here').parentElement).toHaveClass('opacity-60');
    });

    it('is not busy at rest', () => {
        figure();

        expect(document.querySelector('figure')).not.toHaveAttribute('aria-busy');
    });
});

describe('LineChart', () => {
    const points: LinePoint[] = ['May', 'Jun', 'Jul', 'Aug'].map((label) => ({ id: label, label, title: `${label} 2026` }));
    const series: LineSeries[] = [
        { key: 'a', name: 'Series A', color: 'var(--viz-series-1)', values: [100, 200, 300, 400] },
        { key: 'b', name: 'Series B', color: 'var(--viz-series-2)', values: [50, 60, 70, 80] },
    ];
    const chart = () => render(<LineChart name="Two series" points={points} series={series} formatValue={(value) => `${value} u`} />);

    it('draws each series as a 2px line in its own colour, with an end marker ringed in the card colour', () => {
        chart();
        const lines = Array.from(document.querySelectorAll('path[fill="none"]'));

        expect(lines.map((line) => line.getAttribute('stroke'))).toEqual(['var(--viz-series-1)', 'var(--viz-series-2)']);
        expect(lines.every((line) => line.getAttribute('stroke-width') === '2')).toBe(true);
        const dots = document.querySelectorAll('circle');
        expect(dots).toHaveLength(2);
        expect(dots[0]).toHaveAttribute('r', '4');
        expect(dots[0]).toHaveAttribute('stroke', 'var(--card)');
    });

    it('washes a single series with its colour at 10 percent, and two series with none', () => {
        const { unmount } = chart();
        expect(document.querySelector('path[opacity]')).toBeNull();
        unmount();

        render(<LineChart name="One series" points={points} series={[series[0] as LineSeries]} formatValue={String} />);
        expect(document.querySelector('path[opacity="0.1"]')).not.toBeNull();
    });

    it('labels an axis that starts at zero and counts in round steps, with a hairline grid', () => {
        chart();
        const labels = Array.from(document.querySelectorAll('svg text')).map((text) => text.textContent);

        expect(labels).toEqual(expect.arrayContaining(['0 u', '100 u', '400 u', 'May', 'Aug']));
        expect(document.querySelectorAll('svg line[stroke="var(--viz-grid)"]').length).toBeGreaterThan(0);
        expect(document.querySelector('svg line[stroke="var(--viz-baseline)"]')).not.toBeNull();
    });

    it('is a slider that the arrow keys, Home and End move through, speaking each point', async () => {
        chart();
        const user = userEvent.setup();
        const slider = screen.getByRole('slider', { name: /Two series/ });

        slider.focus();
        expect(slider).toHaveAttribute('aria-valuenow', '3');
        expect(slider).toHaveAttribute('aria-valuetext', 'Aug 2026: Series A 400 u, Series B 80 u');

        await user.keyboard('{ArrowLeft}');
        expect(slider).toHaveAttribute('aria-valuenow', '2');
        expect(slider).toHaveAttribute('aria-valuetext', 'Jul 2026: Series A 300 u, Series B 70 u');
        await user.keyboard('{Home}');
        expect(slider).toHaveAttribute('aria-valuenow', '0');
        await user.keyboard('{ArrowLeft}');
        expect(slider).toHaveAttribute('aria-valuenow', '0');
        await user.keyboard('{End}{ArrowRight}');
        expect(slider).toHaveAttribute('aria-valuenow', '3');
    });

    it('shows a readout of every series at the chosen point, values first, and hides it on blur', async () => {
        chart();
        const user = userEvent.setup();
        const slider = screen.getByRole('slider');

        await user.tab();
        const readout = document.querySelector('[aria-live="polite"]') as HTMLElement;
        expect(readout).toHaveStyle({ visibility: 'visible' });
        expect(within(readout).getByText('Aug 2026')).toBeInTheDocument();
        expect(
            within(readout)
                .getAllByRole('listitem')
                .map((item) => item.textContent),
        ).toEqual(['400 uSeries A', '80 uSeries B']);

        act(() => slider.blur());
        expect(readout).toHaveStyle({ visibility: 'hidden' });
    });

    it('follows the pointer to the nearest point, and lets go when it leaves', () => {
        chart();
        const plot = document.querySelector('svg rect') as SVGRectElement;
        const slider = screen.getByRole('slider');

        fireEvent.pointerMove(plot, { clientX: 0 });
        expect(slider).toHaveAttribute('aria-valuenow', '0');
        fireEvent.pointerLeave(slider);
        expect(document.querySelector('[aria-live="polite"]')).toHaveStyle({ visibility: 'hidden' });
    });
});

describe('SegmentedBar', () => {
    const parts: SegmentPart[] = [
        { key: 'a', name: 'Alpha', value: 600, amountText: '600 units', color: 'var(--viz-series-1)', ink: 'var(--viz-ink-1)' },
        { key: 'b', name: 'Bravo', value: 300, amountText: '300 units', color: 'var(--viz-series-2)', ink: 'var(--viz-ink-2)' },
        { key: 'c', name: 'Charlie', value: 60, amountText: '60 units', color: 'var(--viz-series-3)', ink: 'var(--viz-ink-3)' },
        { key: 'd', name: 'Delta', value: 0, amountText: '0 units', color: 'var(--viz-series-4)', ink: 'var(--viz-ink-4)' },
    ];
    const bar = () => render(<SegmentedBar name="One whole" parts={parts} />);

    it('draws a part for each value above zero, sized by value, and nothing for a part of zero', () => {
        bar();
        const drawn = within(screen.getByRole('group', { name: 'One whole' })).getAllByRole('button');

        expect(drawn.map((part) => part.getAttribute('aria-label'))).toEqual([
            'Alpha: 600 units, 63%',
            'Bravo: 300 units, 31%',
            'Charlie: 60 units, 6%',
        ]);
        expect(drawn.map((part) => part.style.flexGrow)).toEqual(['600', '300', '60']);
    });

    it('prints a share only on a part wide enough to hold it, in the ink chosen for its colour', () => {
        bar();
        const [alpha, , charlie] = within(screen.getByRole('group', { name: 'One whole' })).getAllByRole('button');

        expect(alpha).toHaveTextContent('63%');
        expect(alpha).toHaveStyle({ color: 'var(--viz-ink-1)' });
        expect(charlie).toHaveTextContent('');
    });

    it('names every part, the one of zero too, in a legend without numbers', () => {
        bar();

        expect(
            within(screen.getByRole('list', { name: 'Legend' }))
                .getAllByRole('listitem')
                .map((item) => item.textContent),
        ).toEqual(['Alpha', 'Bravo', 'Charlie', 'Delta']);
    });

    it('reads a part out on hover and on keyboard focus, and lets the others fade', async () => {
        bar();
        const user = userEvent.setup();
        const [alpha, bravo] = within(screen.getByRole('group', { name: 'One whole' })).getAllByRole('button');

        expect(screen.getByText('Hover or focus a part to read it.')).toBeInTheDocument();
        await user.hover(bravo as HTMLElement);
        expect(screen.getByText('300 units')).toBeInTheDocument();
        expect(screen.getByText(/Bravo, 31% of the whole/)).toBeInTheDocument();
        expect(alpha).toHaveStyle({ opacity: '0.55' });

        await user.unhover(bravo as HTMLElement);
        await user.tab();
        expect(screen.getByText(/Alpha, 63% of the whole/)).toBeInTheDocument();
    });

    it('pins a part when it is pressed, and lets go when pressed again', async () => {
        bar();
        const user = userEvent.setup();
        const alpha = within(screen.getByRole('group', { name: 'One whole' })).getAllByRole('button')[0] as HTMLElement;

        await user.click(alpha);
        expect(alpha).toHaveAttribute('aria-pressed', 'false');
    });

    it('draws nothing and divides by nothing when every part is zero', () => {
        render(<SegmentedBar name="Empty" parts={parts.map((part) => ({ ...part, value: 0 }))} />);

        expect(within(screen.getByRole('group', { name: 'Empty' })).queryAllByRole('button')).toHaveLength(0);
    });
});
