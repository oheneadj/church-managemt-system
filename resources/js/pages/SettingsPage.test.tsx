import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { SettingsPage } from '@/pages/SettingsPage';
import { churchApi } from '@/services/api/church';
import { ApiError } from '@/services/ApiError';
import { churchProfile, churchSettings } from '@/test/fixtures';
import { renderPage } from '@/test/render';
import { useChurchStore } from '@/stores/useChurchStore';

vi.mock('@/services/api/church');
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }));

function show() {
    return renderPage(<SettingsPage />, { path: ROUTES.SETTINGS, url: ROUTES.SETTINGS, permissions: [PERMISSIONS.CHURCH_SETTINGS] });
}

describe('SettingsPage: church details', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(churchApi.profile).mockResolvedValue(churchProfile());
        vi.mocked(churchApi.settings).mockResolvedValue(churchSettings());
    });

    it('fills the details in from what is saved, and says the web address cannot be changed here', async () => {
        show();

        expect(await screen.findByLabelText(/^Name of the church/)).toHaveValue('Grace Assembly');
        expect(screen.getByLabelText('Denomination')).toHaveValue('Pentecostal');
        expect(screen.getByLabelText('Town or city')).toHaveValue('Kumasi');
        expect(screen.getByLabelText('Phone number')).toHaveValue('+233302000000');
        expect(screen.getByText(/Your web address is grace/)).toBeInTheDocument();
    });

    it('saves a changed name, shows it in the menu, and clears a detail that was emptied', async () => {
        vi.mocked(churchApi.updateProfile).mockResolvedValue(churchProfile({ name: 'Grace Chapel', denomination: null }));
        const user = userEvent.setup();
        show();

        const name = await screen.findByLabelText(/^Name of the church/);
        await user.clear(name);
        await user.type(name, 'Grace Chapel');
        await user.clear(screen.getByLabelText('Denomination'));
        await user.click(screen.getByRole('button', { name: 'Save details' }));

        await waitFor(() =>
            expect(churchApi.updateProfile).toHaveBeenCalledWith({
                name: 'Grace Chapel',
                denomination: null,
                address: '12 Ring Road',
                city: 'Kumasi',
                region: 'Ashanti',
                phone: '+233302000000',
                email: 'office@grace.example',
            }),
        );
        await waitFor(() => expect(useChurchStore.getState().church?.name).toBe('Grace Chapel'));
    });

    it('will not save without a name, and sends nothing', async () => {
        const user = userEvent.setup();
        show();

        await user.clear(await screen.findByLabelText(/^Name of the church/));
        await user.click(screen.getByRole('button', { name: 'Save details' }));

        expect(await screen.findByText('Enter the name of the church')).toBeInTheDocument();
        expect(churchApi.updateProfile).not.toHaveBeenCalled();
    });

    it("puts the server's message beside the box it belongs to", async () => {
        vi.mocked(churchApi.updateProfile).mockRejectedValue(
            new ApiError('Invalid', 422, { phone: ['The phone must be a valid phone number.'] }),
        );
        const user = userEvent.setup();
        show();

        await screen.findByLabelText(/^Name of the church/);
        await user.click(screen.getByRole('button', { name: 'Save details' }));

        expect(await screen.findByText('The phone must be a valid phone number.')).toBeInTheDocument();
    });
});

describe('SettingsPage: preferences', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(churchApi.profile).mockResolvedValue(churchProfile());
        vi.mocked(churchApi.settings).mockResolvedValue(churchSettings());
    });

    it('shows the saved settings, with the absence text off until the church switches it on', async () => {
        show();

        expect(await screen.findByLabelText('Youth are younger than (years)')).toHaveValue(25);
        expect(screen.getByLabelText(/A member is absent after missing/)).toHaveValue(3);
        expect(screen.getByLabelText('Text a receipt when a cash gift is recorded')).toBeChecked();
        expect(screen.getByLabelText('Text members who have gone absent')).not.toBeChecked();
        expect(screen.getByText(/uses your SMS credits/)).toBeInTheDocument();
    });

    it('switches the absence text on and saves every setting', async () => {
        vi.mocked(churchApi.updateSettings).mockResolvedValue(churchSettings({ absent_alert: true }));
        const user = userEvent.setup();
        show();

        await user.click(await screen.findByLabelText('Text members who have gone absent'));
        await user.click(screen.getByRole('button', { name: 'Save preferences' }));

        await waitFor(() =>
            expect(churchApi.updateSettings).toHaveBeenCalledWith({
                youth_age_limit: 25,
                absence_threshold: 3,
                receipt_on_cash: true,
                absent_alert: true,
            }),
        );
    });

    it('refuses a number outside its range before asking the server', async () => {
        const user = userEvent.setup();
        show();

        const box = await screen.findByLabelText(/A member is absent after missing/);
        await user.clear(box);
        await user.type(box, '20');
        await user.click(screen.getByRole('button', { name: 'Save preferences' }));

        expect(await screen.findByText('The number of services must be a whole number from 2 to 12')).toBeInTheDocument();
        expect(churchApi.updateSettings).not.toHaveBeenCalled();
    });

    it('says when the settings cannot be read, and tries again', async () => {
        vi.mocked(churchApi.settings).mockRejectedValueOnce(new ApiError('Something broke.', 500)).mockResolvedValue(churchSettings());
        const user = userEvent.setup();
        show();

        expect(await screen.findByRole('alert')).toHaveTextContent('Something broke.');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByLabelText('Youth are younger than (years)')).toBeInTheDocument();
    });
});

describe('SettingsPage: lists you can change', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(churchApi.profile).mockResolvedValue(churchProfile());
        vi.mocked(churchApi.settings).mockResolvedValue(churchSettings());
    });

    it('offers the giving funds to someone who may manage them', async () => {
        renderPage(<SettingsPage />, {
            path: ROUTES.SETTINGS,
            url: ROUTES.SETTINGS,
            permissions: [PERMISSIONS.CHURCH_SETTINGS, PERMISSIONS.FUND_TYPES_MANAGE],
        });

        expect(await screen.findByRole('link', { name: 'Giving funds' })).toHaveAttribute('href', '/settings/funds');
    });

    it('does not offer them to someone who may not', async () => {
        show();

        await screen.findByLabelText(/^Name of the church/);
        expect(screen.queryByRole('link', { name: 'Giving funds' })).not.toBeInTheDocument();
    });
});
