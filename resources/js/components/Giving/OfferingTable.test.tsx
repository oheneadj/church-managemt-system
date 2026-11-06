import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { OfferingTable } from '@/components/Giving/OfferingTable';
import { offeringSection } from '@/test/fixtures';

describe('OfferingTable', () => {
    it('shows a line for each fund with each payment method and the total, as the API wrote them', () => {
        render(<OfferingTable title="Given at this service" section={offeringSection()} />);

        const tithe = screen.getByRole('row', { name: /Tithe/ });
        expect(
            within(tithe)
                .getAllByRole('cell')
                .map((cell) => cell.textContent),
        ).toEqual(['GHS 1,200.00', 'GHS 850.00', 'GHS 2,050.00']);
        const total = screen.getByRole('row', { name: /^Total/ });
        expect(
            within(total)
                .getAllByRole('cell')
                .map((cell) => cell.textContent),
        ).toEqual(['GHS 1,930.00', 'GHS 1,220.00', 'GHS 3,150.00']);
    });

    it('leaves out a payment method nobody used, so the table stays narrow', () => {
        render(<OfferingTable title="Given at this service" section={offeringSection()} />);

        expect(screen.getByRole('columnheader', { name: 'Cash' })).toBeInTheDocument();
        expect(screen.getByRole('columnheader', { name: 'Mobile Money' })).toBeInTheDocument();
        expect(screen.queryByRole('columnheader', { name: 'Card' })).not.toBeInTheDocument();
        expect(screen.queryByRole('columnheader', { name: 'USSD' })).not.toBeInTheDocument();
    });

    it('shows a method once any fund used it', () => {
        const section = offeringSection({
            by_method: { cash: '1930.00', momo: '1220.00', card: '25.00', ussd: '0.00' },
            by_method_formatted: { cash: 'GHS 1,930.00', momo: 'GHS 1,220.00', card: 'GHS 25.00', ussd: 'GHS 0.00' },
        });
        render(<OfferingTable title="Given at this service" section={section} />);

        expect(screen.getByRole('columnheader', { name: 'Card' })).toBeInTheDocument();
    });

    it('counts the gifts and the anonymous ones, in the singular for one', () => {
        const { rerender } = render(<OfferingTable title="Given at this service" section={offeringSection()} />);
        expect(screen.getByText('42 gifts, 7 anonymous')).toBeInTheDocument();

        rerender(<OfferingTable title="Given at this service" section={offeringSection({ transaction_count: 1, anonymous_count: 0 })} />);
        expect(screen.getByText('1 gift, 0 anonymous')).toBeInTheDocument();
    });

    it('is a labelled table section with its title as the heading', () => {
        render(<OfferingTable title="Given at this service" section={offeringSection()} />);

        expect(screen.getByRole('region', { name: 'Given at this service' })).toBeInTheDocument();
    });
});
