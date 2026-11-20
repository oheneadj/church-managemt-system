import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTE_PATTERNS } from '@/constants/routes';
import { OfferingReportPage } from '@/pages/OfferingReportPage';
import { ApiError } from '@/services/ApiError';
import { offeringReportApi } from '@/services/api/offeringReport';
import { offeringReport, offeringSection } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/offeringReport');

function open() {
    return renderPage(<OfferingReportPage />, {
        path: ROUTE_PATTERNS.SERVICE_OFFERING,
        url: '/services/service-1/offering',
        permissions: [PERMISSIONS.GIVING_SUMMARY],
    });
}

const NO_GIFTS = offeringSection({
    grand_total: '0.00',
    grand_total_formatted: 'GHS 0.00',
    by_fund: [],
    by_method: { cash: '0.00', momo: '0.00', card: '0.00', ussd: '0.00' },
    by_method_formatted: { cash: 'GHS 0.00', momo: 'GHS 0.00', card: 'GHS 0.00', ussd: 'GHS 0.00' },
    transaction_count: 0,
    anonymous_count: 0,
});

describe('OfferingReportPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(offeringReportApi.get).mockResolvedValue(offeringReport());
    });

    it('shows the church, the service, the date, each fund and the total for the day', async () => {
        open();

        expect(await screen.findByRole('heading', { level: 1, name: 'Offering report' })).toBeInTheDocument();
        expect(screen.getByText(/Grace Assembly · Sunday Morning Service · 25 Oct 2026/)).toBeInTheDocument();
        expect(screen.getByRole('region', { name: 'Given at this service' })).toBeInTheDocument();
        expect(screen.getByText('Tithe')).toBeInTheDocument();
        expect(screen.getByText('Total for the day').closest('p')).toHaveTextContent('GHS 3,150.00');
        expect(screen.getByText('Voided gifts are not counted.')).toBeInTheDocument();
        expect(offeringReportApi.get).toHaveBeenCalledWith('service-1');
    });

    it('shows the gifts given that day with no service as a block of their own, when they are counted here', async () => {
        vi.mocked(offeringReportApi.get).mockResolvedValue(
            offeringReport({
                no_service: {
                    counted_here: true,
                    ...offeringSection({
                        grand_total: '75.50',
                        grand_total_formatted: 'GHS 75.50',
                        transaction_count: 2,
                        anonymous_count: 1,
                    }),
                },
                day_grand_total: '3225.50',
                day_grand_total_formatted: 'GHS 3,225.50',
            }),
        );
        open();

        expect(await screen.findByRole('region', { name: 'Given that day with no service' })).toBeInTheDocument();
        expect(screen.getByText('Total for the day').closest('p')).toHaveTextContent('GHS 3,225.50');
    });

    it("leaves that block out when the day's gifts are counted with another service", async () => {
        vi.mocked(offeringReportApi.get).mockResolvedValue(offeringReport({ no_service: { counted_here: false, ...NO_GIFTS } }));
        open();

        await screen.findByRole('region', { name: 'Given at this service' });
        expect(screen.queryByRole('region', { name: 'Given that day with no service' })).not.toBeInTheDocument();
    });

    it('says plainly when nothing was given', async () => {
        vi.mocked(offeringReportApi.get).mockResolvedValue(
            offeringReport({
                totals: NO_GIFTS,
                no_service: { counted_here: true, ...NO_GIFTS },
                day_grand_total: '0.00',
                day_grand_total_formatted: 'GHS 0.00',
            }),
        );
        open();

        expect(await screen.findByText('Nothing was given')).toBeInTheDocument();
        expect(screen.queryByText('Total for the day')).not.toBeInTheDocument();
    });

    it('opens the browser print window from the print button', async () => {
        const print = vi.spyOn(window, 'print').mockImplementation(() => undefined);
        const user = userEvent.setup();
        open();

        await user.click(await screen.findByRole('button', { name: 'Print or save as PDF' }));

        expect(print).toHaveBeenCalledTimes(1);
        print.mockRestore();
    });

    it('says the report was not found when the server refuses it, with no retry button', async () => {
        vi.mocked(offeringReportApi.get).mockRejectedValue(new ApiError('This action is unauthorized.', 403));
        open();

        expect(await screen.findByText('This report was not found.')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /try again/i })).not.toBeInTheDocument();
    });

    it('offers a retry on a server error', async () => {
        vi.mocked(offeringReportApi.get).mockRejectedValue(new ApiError('Something went wrong. Please try again.', 500));
        open();

        expect(await screen.findByText('Something went wrong. Please try again.')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
    });
});
