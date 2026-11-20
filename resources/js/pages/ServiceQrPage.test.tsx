import { screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTE_PATTERNS } from '@/constants/routes';
import { ServiceQrPage } from '@/pages/ServiceQrPage';
import { ApiError } from '@/services/ApiError';
import { servicesApi } from '@/services/api/services';
import { qrCode, service } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/services');

function open() {
    return renderPage(<ServiceQrPage />, {
        path: ROUTE_PATTERNS.SERVICE_QR,
        url: '/services/service-1/qr',
        permissions: [PERMISSIONS.MEMBERS_EDIT],
    });
}

describe('ServiceQrPage', () => {
    beforeEach(() => {
        vi.mocked(servicesApi.get).mockResolvedValue(service({ is_open: true }));
        vi.mocked(servicesApi.qr).mockResolvedValue(qrCode());
    });

    it('shows a large QR code for the service, with the address in words and a way back', async () => {
        open();

        expect(await screen.findByRole('heading', { level: 1, name: 'Sunday Morning Service' })).toBeInTheDocument();
        expect(await screen.findByTitle('QR code to check in to this service')).toBeInTheDocument();
        expect(screen.getByText(/Scan with your phone camera/)).toBeInTheDocument();
        expect(screen.getByText(`http://grace.localhost/checkin/${'a'.repeat(32)}`)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Back to the service' })).toHaveAttribute('href', '/services/service-1');
    });

    it('has no menu around it', async () => {
        open();

        await screen.findByTitle('QR code to check in to this service');
        expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
    });

    it('says check-in is closed instead of showing a code when the service is not open', async () => {
        vi.mocked(servicesApi.qr).mockRejectedValue(new ApiError('Check-in is closed for this service.', 409));
        open();

        expect(await screen.findByText('Check-in is closed.')).toBeInTheDocument();
        expect(screen.queryByTitle('QR code to check in to this service')).not.toBeInTheDocument();
    });

    it('shows other problems with a retry', async () => {
        vi.mocked(servicesApi.qr).mockRejectedValue(new ApiError('The server is busy.', 500));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        await waitFor(() => expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument());
    });
});
