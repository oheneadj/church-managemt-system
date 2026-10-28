import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ROUTES } from '@/constants/routes';
import { StartChurchPage } from '@/pages/StartChurchPage';
import { onboardingApi } from '@/services/api/onboarding';
import { ApiError } from '@/services/ApiError';
import type { StartedChurch } from '@/types/onboarding';

vi.mock('@/services/api/onboarding');

const started: StartedChurch = {
    church: {
        id: '01',
        name: 'Victory Chapel',
        slug: 'victory-chapel',
        member_number_prefix: 'VCI',
        url: 'https://victory-chapel.example.com',
    },
    admin: { id: '02', name: 'Kofi Mensah' },
    login_url: 'https://victory-chapel.example.com/login',
};

function renderPage() {
    const router = createMemoryRouter([{ path: ROUTES.START, element: <StartChurchPage /> }], { initialEntries: [ROUTES.START] });

    render(
        <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );
}

async function fillDetails() {
    await userEvent.type(screen.getByLabelText(/Church name/), 'Victory Chapel');
    await userEvent.type(screen.getByLabelText(/Member number letters/), 'vci');
    await userEvent.type(screen.getByLabelText(/Your name/), 'Kofi Mensah');
    await userEvent.type(screen.getByLabelText(/Your phone number/), '0244300001');
}

describe('StartChurchPage', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows the labelled boxes and no code box until a code is sent', () => {
        renderPage();

        expect(screen.getByLabelText(/Church name/)).toBeRequired();
        expect(screen.getByLabelText(/Web address/)).toHaveAttribute('placeholder');
        expect(screen.queryByLabelText(/Code from the text message/)).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Start my church' })).toBeDisabled();
    });

    it('checks the phone number before asking for a code', async () => {
        renderPage();

        await userEvent.click(screen.getByRole('button', { name: 'Text me a code' }));

        expect(await screen.findByText('Enter a valid phone number')).toBeInTheDocument();
        expect(onboardingApi.requestOtp).not.toHaveBeenCalled();
    });

    it('texts a code, then starts the church and offers the link to sign in', async () => {
        vi.mocked(onboardingApi.requestOtp).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
        vi.mocked(onboardingApi.startChurch).mockResolvedValue(started);
        renderPage();

        await fillDetails();
        await userEvent.click(screen.getByRole('button', { name: 'Text me a code' }));
        await userEvent.type(await screen.findByLabelText(/Code from the text message/), '123456');
        await userEvent.click(screen.getByRole('button', { name: 'Start my church' }));

        expect(onboardingApi.requestOtp).toHaveBeenCalledWith('0244300001');
        expect(onboardingApi.startChurch).toHaveBeenCalledWith(
            expect.objectContaining({ church_name: 'Victory Chapel', member_number_prefix: 'vci', otp: '123456', slug: undefined }),
        );
        expect(await screen.findByRole('link', { name: 'Go to my church' })).toHaveAttribute('href', started.login_url);
        expect(screen.getByText(/second admin/)).toBeInTheDocument();
    });

    it('puts a taken web address message next to the web address box', async () => {
        vi.mocked(onboardingApi.requestOtp).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
        vi.mocked(onboardingApi.startChurch).mockRejectedValue(
            new ApiError('taken', 422, { slug: ['This web address is already taken. Try victory-gh.'] }),
        );
        renderPage();

        await fillDetails();
        await userEvent.type(screen.getByLabelText(/Web address/), 'victory');
        await userEvent.click(screen.getByRole('button', { name: 'Text me a code' }));
        await userEvent.type(await screen.findByLabelText(/Code from the text message/), '123456');
        await userEvent.click(screen.getByRole('button', { name: 'Start my church' }));

        expect(await screen.findByText('This web address is already taken. Try victory-gh.')).toBeInTheDocument();
        expect(screen.getByLabelText(/Web address/)).toHaveAccessibleDescription(/Try victory-gh/);
    });

    it('puts a wrong-code message next to the code box', async () => {
        vi.mocked(onboardingApi.requestOtp).mockResolvedValue({ message: 'OTP sent', expires_in: 600 });
        vi.mocked(onboardingApi.startChurch).mockRejectedValue(new ApiError('Invalid code', 422));
        renderPage();

        await fillDetails();
        await userEvent.click(screen.getByRole('button', { name: 'Text me a code' }));
        await userEvent.type(await screen.findByLabelText(/Code from the text message/), '000000');
        await userEvent.click(screen.getByRole('button', { name: 'Start my church' }));

        await waitFor(() => expect(screen.getByLabelText(/Code from the text message/)).toHaveAccessibleDescription('Invalid code'));
    });

    it('tells the user when the code could not be sent', async () => {
        vi.mocked(onboardingApi.requestOtp).mockRejectedValue(new ApiError('Too many requests.', 429));
        renderPage();

        await userEvent.type(screen.getByLabelText(/Your phone number/), '0244300001');
        await userEvent.click(screen.getByRole('button', { name: 'Text me a code' }));

        expect(await screen.findByRole('alert')).toHaveTextContent('Too many requests.');
        expect(screen.queryByLabelText(/Code from the text message/)).not.toBeInTheDocument();
    });
});
