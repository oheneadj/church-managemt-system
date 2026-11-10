import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { StepIndicator } from '@/components/ui/StepIndicator';

// Progress through a form with steps (docs/design-system/interactions.md, section 7): the words "Step 2 of 3", a bar and the name of
// every step, the current one marked for screen readers, and a step already done said in words as well as shown.

const TITLES = ['Who they are', 'How to reach them', 'Church life'];

function steps() {
    return within(screen.getByRole('list', { name: 'Steps' })).getAllByRole('listitem');
}

describe('StepIndicator', () => {
    it('says which step it is and how many there are', () => {
        render(<StepIndicator titles={TITLES} current={1} />);

        expect(screen.getByText('Step 2 of 3')).toBeInTheDocument();
    });

    it('names every step, numbered, in order', () => {
        render(<StepIndicator titles={TITLES} current={0} />);

        expect(steps().map((step) => step.textContent)).toEqual(['1. Who they are', '2. How to reach them', '3. Church life']);
    });

    it('marks only the current step for screen readers, and shows it bold', () => {
        render(<StepIndicator titles={TITLES} current={1} />);
        const [first, second, third] = steps();

        expect(second).toHaveAttribute('aria-current', 'step');
        expect(first).not.toHaveAttribute('aria-current');
        expect(third).not.toHaveAttribute('aria-current');
        expect(
            within(second as HTMLElement)
                .getByText(/How to reach them/)
                .closest('span.flex'),
        ).toHaveClass('font-semibold');
    });

    it('fills the bar up to and including the current step, and leaves the later ones quiet', () => {
        render(<StepIndicator titles={TITLES} current={1} />);
        const bars = steps().map((step) => step.querySelector('span[aria-hidden="true"]'));

        expect(bars.map((bar) => bar?.className.includes('bg-primary'))).toEqual([true, true, false]);
        expect(bars[2]).toHaveClass('bg-muted');
    });

    it('ticks a step that is done and says so in hidden words, and does not tick the current or later ones', () => {
        render(<StepIndicator titles={TITLES} current={2} />);
        const [first, second, third] = steps();

        expect(first?.querySelector('svg')).toHaveAttribute('aria-hidden', 'true');
        expect(first).toHaveTextContent('1. Who they are (done)');
        expect(second?.querySelector('svg')).not.toBeNull();
        expect(third?.querySelector('svg')).toBeNull();
        expect(third).not.toHaveTextContent('(done)');
    });

    it('ticks nothing on the first step', () => {
        render(<StepIndicator titles={TITLES} current={0} />);

        expect(document.querySelectorAll('li svg')).toHaveLength(0);
    });

    it('gives each step an equal share of the width', () => {
        render(<StepIndicator titles={TITLES} current={0} />);

        expect(screen.getByRole('list', { name: 'Steps' })).toHaveStyle({ gridTemplateColumns: 'repeat(3, minmax(0, 1fr))' });
    });
});
