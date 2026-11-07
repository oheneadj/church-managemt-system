import { screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { DashboardPage } from '@/pages/DashboardPage';
import { ApiError } from '@/services/ApiError';
import { membersApi } from '@/services/api/members';
import { reportsApi } from '@/services/api/reports';
import { givingByMonth, memberSummary, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';
import type { AttendanceReport } from '@/types/reports';

vi.mock('@/services/api/members');
vi.mock('@/services/api/reports');

/** Services, newest first, as `GET /reports/attendance?group_by=service` sends them. */
const byService: AttendanceReport = {
    group_by: 'service',
    data: [
        { service_id: 'service-9', service_date: '2026-11-01', service_name: 'Sunday Morning Service', total: 118 },
        { service_id: 'service-8', service_date: '2026-10-25', service_name: 'Sunday Morning Service', total: 104 },
    ],
    summary: { average: 111, highest: 118, lowest: 104 },
};

const EVERYTHING: Permission[] = [PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.REPORTS_VIEW, PERMISSIONS.GIVING_SUMMARY];

function open(permissions: Permission[] = EVERYTHING) {
    return renderPage(<DashboardPage />, { path: ROUTES.DASHBOARD, url: ROUTES.DASHBOARD, permissions });
}

/** The card with this label, as the link that wraps it. */
function card(label: string): HTMLElement {
    return screen.getByText(label).closest('a') as HTMLElement;
}

describe('DashboardPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary()], { total: 212 }));
        vi.mocked(reportsApi.attendance).mockResolvedValue(byService);
        vi.mocked(reportsApi.giving).mockResolvedValue(givingByMonth({ data: [givingByMonth().data[0]!] }));
    });

    it('welcomes the person and names the church', async () => {
        open();

        expect(await screen.findByRole('heading', { level: 1, name: 'Welcome, Akosua Mensah' })).toBeInTheDocument();
        expect(screen.getByText('Here is how Grace Assembly is doing.')).toBeInTheDocument();
    });

    it("shows the active members, the last service and this month's giving, each leading to the page behind it", async () => {
        open();

        expect(
            await within(await screen.findByText('Active members').then(() => card('Active members'))).findByText('212'),
        ).toBeInTheDocument();
        expect(card('Active members')).toHaveAttribute('href', '/members?status=active');

        const service = card('Last service');
        expect(await within(service).findByText('118')).toBeInTheDocument();
        expect(within(service).getByText('Sunday Morning Service, 1 Nov 2026')).toBeInTheDocument();
        expect(service).toHaveAttribute('href', '/services/service-9');

        const giving = card('Given this month');
        expect(await within(giving).findByText('GHS 15,450.00')).toBeInTheDocument();
        expect(within(giving).getByText('October 2026')).toBeInTheDocument();
        expect(giving).toHaveAttribute('href', '/reports?tab=giving');
    });

    it('asks for the active members only, and the reports for one service list and one month', async () => {
        open();
        await screen.findByText('GHS 15,450.00');

        expect(membersApi.list).toHaveBeenCalledWith({ search: '', status: 'active', gender: '', department_id: '', page: 1 });
        expect(reportsApi.attendance).toHaveBeenCalledWith({ weeks: 12, groupBy: 'service' });
        expect(reportsApi.giving).toHaveBeenCalledWith({ months: 1, fundId: '' });
    });

    it('shows a department head only the members, and never asks for a report they may not read', async () => {
        open([PERMISSIONS.MEMBERS_VIEW]);

        await screen.findByText('212');
        expect(screen.queryByText('Last service')).not.toBeInTheDocument();
        expect(screen.queryByText('Given this month')).not.toBeInTheDocument();
        expect(reportsApi.attendance).not.toHaveBeenCalled();
        expect(reportsApi.giving).not.toHaveBeenCalled();
    });

    it('shows attendance without giving to someone who may read reports but not giving totals, and the reverse', async () => {
        const first = open([PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.REPORTS_VIEW]);
        await screen.findByText('118');
        expect(screen.queryByText('Given this month')).not.toBeInTheDocument();
        first.unmount();

        open([PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.GIVING_SUMMARY]);
        await screen.findByText('GHS 15,450.00');
        expect(screen.queryByText('Last service')).not.toBeInTheDocument();
    });

    it('says plainly when no attendance has been recorded yet, and leads to the services', async () => {
        vi.mocked(reportsApi.attendance).mockResolvedValue({
            group_by: 'service',
            data: [],
            summary: { average: 0, highest: 0, lowest: 0 },
        });
        open();

        expect(await screen.findByText('No attendance recorded yet')).toBeInTheDocument();
        expect(card('Last service')).toHaveAttribute('href', '/services');
    });

    it('keeps the other cards when one cannot be read, and offers to try that one again', async () => {
        vi.mocked(reportsApi.giving)
            .mockRejectedValueOnce(new ApiError('The server is busy.', 500))
            .mockResolvedValue(givingByMonth({ data: [givingByMonth().data[0]!] }));
        const { container } = open();

        expect(await screen.findByRole('alert')).toHaveTextContent('Given this month: The server is busy.');
        expect(await screen.findByText('212')).toBeInTheDocument();
        expect(screen.getByText('118')).toBeInTheDocument();
        expect(container.querySelectorAll('[role="alert"]')).toHaveLength(1);

        screen.getByRole('button', { name: 'Try again' }).click();
        expect(await screen.findByText('GHS 15,450.00')).toBeInTheDocument();
    });

    it('shows a loading placeholder for a card while its figure is on its way', async () => {
        vi.mocked(reportsApi.giving).mockReturnValue(new Promise(() => undefined));
        open();

        expect(await screen.findByLabelText('Given this month is loading')).toBeInTheDocument();
        expect(await screen.findByText('212')).toBeInTheDocument();
    });
});
