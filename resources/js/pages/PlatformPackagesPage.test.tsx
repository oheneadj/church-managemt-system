import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { toast } from 'sonner';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROUTES } from '@/constants/routes';
import { PlatformPackagesPage } from '@/pages/PlatformPackagesPage';
import { ApiError } from '@/services/ApiError';
import { platformApi } from '@/services/api/platform';
import { smsPackage } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/platform');
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

const starter = smsPackage({ id: 'p1', name: 'Starter', credits: 100, price: '12.50', price_formatted: 'GHS 12.50', sort_order: 1 });
const large = smsPackage({
    id: 'p2',
    name: 'Large',
    credits: 10000,
    price: '800.00',
    price_formatted: 'GHS 800.00',
    sort_order: 2,
    is_active: false,
});

function open() {
    return renderPage(<PlatformPackagesPage />, { path: ROUTES.PLATFORM_PACKAGES, url: ROUTES.PLATFORM_PACKAGES });
}

const row = (name: string) => screen.getByText(name).closest('li') as HTMLElement;

describe('PlatformPackagesPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(platformApi.packages).mockResolvedValue([starter, large]);
    });

    it('lists each package with its credits, its price as the server formatted it, and whether it is on sale', async () => {
        open();

        await screen.findByText('Starter');
        expect(within(row('Starter')).getByText(/100 credits/)).toBeInTheDocument();
        expect(within(row('Starter')).getByText('GHS 12.50')).toBeInTheDocument();
        expect(within(row('Starter')).getByText('On sale')).toBeInTheDocument();
        expect(within(row('Large')).getByText(/10,000 credits/)).toBeInTheDocument();
        expect(within(row('Large')).getByText('Switched off')).toBeInTheDocument();
    });

    it('adds a package after the last, sending the price as the exact text typed', async () => {
        vi.mocked(platformApi.createPackage).mockResolvedValue(smsPackage({ id: 'p3', name: 'Medium' }));
        const user = userEvent.setup();
        open();
        await screen.findByText('Starter');

        await user.click(screen.getByRole('button', { name: 'Add a package' }));
        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByLabelText('Place in the list')).toHaveValue(3);
        await user.type(within(dialog).getByLabelText(/^Name/), 'Medium');
        await user.type(within(dialog).getByLabelText(/^Credits/), '500');
        await user.type(within(dialog).getByLabelText(/^Price/), '45.50');
        await user.click(within(dialog).getByRole('button', { name: 'Add package' }));

        await waitFor(() =>
            expect(platformApi.createPackage).toHaveBeenCalledWith({ name: 'Medium', credits: 500, price: '45.50', sort_order: 3 }),
        );
        expect(toast.success).toHaveBeenCalledWith('Medium is added.');
        await waitFor(() => expect(platformApi.packages).toHaveBeenCalledTimes(2));
    });

    it("refuses a bad price before sending, and shows the server's message when a name is taken", async () => {
        vi.mocked(platformApi.createPackage).mockRejectedValue(
            new ApiError('Invalid', 422, { name: ['A package with this name already exists.'] }),
        );
        const user = userEvent.setup();
        open();
        await screen.findByText('Starter');

        await user.click(screen.getByRole('button', { name: 'Add a package' }));
        const dialog = await screen.findByRole('dialog');
        await user.type(within(dialog).getByLabelText(/^Name/), 'STARTER');
        await user.type(within(dialog).getByLabelText(/^Credits/), '10');
        await user.type(within(dialog).getByLabelText(/^Price/), '0.00');
        await user.click(within(dialog).getByRole('button', { name: 'Add package' }));
        expect(await within(dialog).findByText(/The price must be above 0/)).toBeInTheDocument();
        expect(platformApi.createPackage).not.toHaveBeenCalled();

        await user.clear(within(dialog).getByLabelText(/^Price/));
        await user.type(within(dialog).getByLabelText(/^Price/), '5.00');
        await user.click(within(dialog).getByRole('button', { name: 'Add package' }));
        expect(await within(dialog).findByText('A package with this name already exists.')).toBeInTheDocument();
    });

    it('changes a package from what it is now', async () => {
        vi.mocked(platformApi.updatePackage).mockResolvedValue(starter);
        const user = userEvent.setup();
        open();
        await screen.findByText('Starter');

        await user.click(within(row('Starter')).getByRole('button', { name: 'Change' }));
        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByLabelText(/^Price/)).toHaveValue('12.50');
        await user.clear(within(dialog).getByLabelText(/^Price/));
        await user.type(within(dialog).getByLabelText(/^Price/), '15.00');
        await user.click(within(dialog).getByRole('button', { name: 'Save changes' }));

        await waitFor(() =>
            expect(platformApi.updatePackage).toHaveBeenCalledWith('p1', { name: 'Starter', credits: 100, price: '15.00', sort_order: 1 }),
        );
    });

    it('asks before switching a package off, and says nothing already bought changes', async () => {
        vi.mocked(platformApi.updatePackage).mockResolvedValue({ ...starter, is_active: false });
        const user = userEvent.setup();
        open();
        await screen.findByText('Starter');

        await user.click(within(row('Starter')).getByRole('button', { name: 'Switch off' }));
        const question = await screen.findByRole('alertdialog');
        expect(question).toHaveTextContent('Credits already bought stay with the church');
        expect(platformApi.updatePackage).not.toHaveBeenCalled();

        await user.click(within(question).getByRole('button', { name: 'Switch off' }));

        await waitFor(() => expect(platformApi.updatePackage).toHaveBeenCalledWith('p1', { is_active: false }));
    });

    it('switches a package back on without asking', async () => {
        vi.mocked(platformApi.updatePackage).mockResolvedValue({ ...large, is_active: true });
        const user = userEvent.setup();
        open();
        await screen.findByText('Large');

        await user.click(within(row('Large')).getByRole('button', { name: 'Switch on' }));

        await waitFor(() => expect(platformApi.updatePackage).toHaveBeenCalledWith('p2', { is_active: true }));
        expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument();
    });

    it('has no delete anywhere', async () => {
        open();
        await screen.findByText('Starter');

        expect(screen.queryByRole('button', { name: /delete|remove/i })).not.toBeInTheDocument();
    });

    it('says plainly when there are no packages', async () => {
        vi.mocked(platformApi.packages).mockResolvedValue([]);
        open();

        expect(await screen.findByText('No packages yet')).toBeInTheDocument();
    });

    it('says what went wrong and tries again', async () => {
        vi.mocked(platformApi.packages).mockRejectedValueOnce(new ApiError('The server is busy.', 500)).mockResolvedValue([starter]);
        const user = userEvent.setup();
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByText('Starter')).toBeInTheDocument();
    });
});
