import { render, screen } from '@testing-library/react';
import { TargetIcon, UsersIcon } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import { Meter } from '@/components/Charts/Meter';
import { MiniColumns } from '@/components/Charts/MiniColumns';
import { StatDeltaLine } from '@/components/Charts/StatDeltaLine';
import { FeaturedStatCard, IconStatCard, ProgressStatCard } from '@/components/Charts/StatCardVariants';

// The four stat card designs (docs/design-system/foundations.md, 5a): trend, icon with a mini chart, progress with a meter, and the
// featured card with the one hero figure. The icon never carries meaning, the change is never colour alone, and the meter is a real meter.

describe('StatDeltaLine', () => {
    it('takes no meaning-colour on a filled card, where the arrow and the words carry the change', () => {
        render(<StatDeltaLine delta={{ text: '+8%', direction: 'up', against: 'vs last month' }} onFilled />);
        const line = document.querySelector('[data-slot="stat-delta"]');

        expect(line).not.toHaveClass('text-feedback-success');
        expect(line).not.toHaveClass('text-feedback-error');
        expect(line).toHaveTextContent('Up +8% vs last month');
    });

    it('is coloured by what the change means on a plain card', () => {
        render(<StatDeltaLine delta={{ text: '+8%', direction: 'up', upIsGood: false, against: 'vs last month' }} />);

        expect(document.querySelector('[data-slot="stat-delta"]')).toHaveClass('text-feedback-error');
    });
});

describe('MiniColumns', () => {
    it('draws a thin rounded column for each value, the current one in the first chart colour and the others quiet', () => {
        render(<MiniColumns values={[4, 6, 5, 9]} label="Rising over four periods" />);
        const bars = Array.from(screen.getByRole('img', { name: 'Rising over four periods' }).querySelectorAll('rect'));

        expect(bars).toHaveLength(4);
        expect(bars.map((bar) => bar.getAttribute('fill'))).toEqual([
            'var(--viz-other)',
            'var(--viz-other)',
            'var(--viz-other)',
            'var(--viz-series-1)',
        ]);
        expect(bars.every((bar) => bar.getAttribute('rx') === '4' && bar.getAttribute('width') === '8')).toBe(true);
    });

    it('sits every column on one baseline, taller for larger values, and never draws a column of nothing', () => {
        render(<MiniColumns values={[0, 5, 10]} label="x" />);
        const bars = Array.from(screen.getByRole('img').querySelectorAll('rect'));
        const bottoms = bars.map((bar) => Number(bar.getAttribute('y')) + Number(bar.getAttribute('height')));

        expect(new Set(bottoms).size).toBe(1);
        expect(Number(bars[0]?.getAttribute('height'))).toBeGreaterThanOrEqual(4);
        expect(Number(bars[2]?.getAttribute('height'))).toBeGreaterThan(Number(bars[1]?.getAttribute('height')));
    });
});

describe('Meter', () => {
    it('is a real meter with its reading in words', () => {
        render(<Meter value={720} max={1000} label="Monthly goal" valueText="720 of 1,000 units" />);
        const meter = screen.getByRole('meter', { name: 'Monthly goal' });

        expect(meter).toHaveAttribute('aria-valuemin', '0');
        expect(meter).toHaveAttribute('aria-valuemax', '1000');
        expect(meter).toHaveAttribute('aria-valuenow', '720');
        expect(meter).toHaveAttribute('aria-valuetext', '720 of 1,000 units');
        expect(meter.firstElementChild).toHaveStyle({ width: '72%' });
    });

    it.each([
        ['past the goal', 1500, 1000, '100%', '1000'],
        ['below nothing', -50, 1000, '0%', '0'],
        ['with no goal set', 5, 0, '0%', '0'],
    ])('stays inside its track %s', (_name, value, max, width, now) => {
        render(<Meter value={value} max={max} label="x" valueText="x" />);

        expect(screen.getByRole('meter').firstElementChild).toHaveStyle({ width });
        expect(screen.getByRole('meter')).toHaveAttribute('aria-valuenow', now);
    });

    it('draws its track as a lighter step of the fill colour, so the bar reads as one thing', () => {
        render(<Meter value={1} max={2} label="x" valueText="x" />);

        expect(screen.getByRole('meter').getAttribute('style')).toContain('var(--viz-series-1) 18%');
        expect(screen.getByRole('meter').firstElementChild).toHaveStyle({ background: 'var(--viz-series-1)' });
    });
});

describe('IconStatCard', () => {
    it('shows the label, the number and the change, with the icon as decoration only', () => {
        render(
            <IconStatCard
                icon={UsersIcon}
                label="Weekly visits"
                value="436"
                delta={{ text: '-4.2%', direction: 'down', against: 'vs last week' }}
                columns={{ values: [3, 4, 5], label: 'Three weeks' }}
            />,
        );

        expect(screen.getByText('Weekly visits')).toBeInTheDocument();
        expect(screen.getByText('436')).toBeInTheDocument();
        expect(document.querySelector('[data-slot="stat-delta"]')).toHaveTextContent('Down -4.2% vs last week');
        expect(screen.getByRole('img', { name: 'Three weeks' })).toBeInTheDocument();
        expect(document.querySelector('span[aria-hidden="true"] svg')).not.toBeNull();
    });

    it('leaves out the change and the small chart when it has none', () => {
        render(<IconStatCard icon={UsersIcon} label="L" value="1" />);

        expect(document.querySelector('[data-slot="stat-delta"]')).toBeNull();
        expect(screen.queryByRole('img')).toBeNull();
    });
});

describe('ProgressStatCard', () => {
    it('shows one number against its goal as a meter and says the same in words under it', () => {
        render(
            <ProgressStatCard
                icon={TargetIcon}
                label="Monthly goal"
                value="72%"
                progress={{ value: 720, max: 1000, label: 'Monthly goal progress', text: '720 of 1,000 units' }}
            />,
        );

        expect(screen.getByText('72%')).toBeInTheDocument();
        expect(screen.getByRole('meter', { name: 'Monthly goal progress' })).toHaveAttribute('aria-valuetext', '720 of 1,000 units');
        expect(screen.getAllByText('720 of 1,000 units')).toHaveLength(1);
    });
});

describe('FeaturedStatCard', () => {
    it('is the deep filled card with light text and the hero figure', () => {
        render(
            <FeaturedStatCard
                label="Total this month"
                value="24,310"
                delta={{ text: '+8.1%', direction: 'up', against: 'vs last month' }}
                trend={{ values: [1, 2, 3], label: 'Rising' }}
            />,
        );
        const card = document.querySelector('[data-variant="featured"]');

        expect(card).toHaveClass('bg-primary', 'text-primary-foreground');
        expect(screen.getByText('24,310')).toHaveClass('text-hero');
        expect(card?.querySelector('span[aria-hidden="true"].bg-gold')).not.toBeNull();
        expect(screen.getByRole('img', { name: 'Rising' })).toBeInTheDocument();
    });

    it('draws its trend in light ink so it reads on the deep fill', () => {
        render(<FeaturedStatCard label="L" value="1" trend={{ values: [1, 2, 3], label: 'Rising' }} />);

        expect(screen.getByRole('img', { name: 'Rising' }).querySelector('line')).toHaveAttribute('stroke', 'currentColor');
    });
});
