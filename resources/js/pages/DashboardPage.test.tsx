import { screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { DashboardPage } from '@/pages/DashboardPage';
import { ApiError } from '@/services/ApiError';
import { activityApi } from '@/services/api/activity';
import { attendanceApi } from '@/services/api/attendance';
import { membersApi } from '@/services/api/members';
import { reportsApi } from '@/services/api/reports';
import { absentMember, activityLine, givingByMonth, memberSummary, pageOf } from '@/test/fixtures';
import { renderPage } from '@/test/render';
import type { AttendanceReport } from '@/types/reports';

vi.mock('@/services/api/activity');
vi.mock('@/services/api/attendance');
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

/** The same, once it has been drawn. */
async function findCard(label: string): Promise<HTMLElement> {
    await screen.findByText(label);

    return card(label);
}

describe('DashboardPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary()], { total: 212 }));
        vi.mocked(reportsApi.attendance).mockResolvedValue(byService);
        vi.mocked(attendanceApi.absent).mockResolvedValue(pageOf([absentMember()], { total: 7 }));
        vi.mocked(activityApi.recent).mockResolvedValue(
            pageOf([
                activityLine(),
                activityLine({
                    id: 'activity-2',
                    kind: 'gift_recorded',
                    text: 'A gift of GHS 1,250.50 to Building Fund was recorded.',
                    actor_name: null,
                    occurred_at: null,
                }),
            ]),
        );
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

    it('shows how many members have gone absent, by the church setting, leading to the list', async () => {
        open();

        await screen.findByText('Absent members');
        const absent = card('Absent members');
        expect(within(absent).getByText('7')).toBeInTheDocument();
        expect(absent).toHaveAttribute('href', '/attendance/absent');
        expect(attendanceApi.absent).toHaveBeenCalledWith({ consecutive: null, page: 1 });
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
        expect(screen.queryByText('Absent members')).not.toBeInTheDocument();
        expect(screen.queryByText('Recent activity')).not.toBeInTheDocument();
        expect(activityApi.recent).not.toHaveBeenCalled();
        expect(reportsApi.attendance).not.toHaveBeenCalled();
        expect(attendanceApi.absent).not.toHaveBeenCalled();
        expect(reportsApi.giving).not.toHaveBeenCalled();
    });

    it('shows attendance without giving to someone who may read reports but not giving totals, and the reverse', async () => {
        const first = open([PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.REPORTS_VIEW]);
        await within(await screen.findByText('Last service').then(() => card('Last service'))).findByText('118');
        expect(screen.queryByText('Given this month')).not.toBeInTheDocument();
        first.unmount();

        open([PERMISSIONS.MEMBERS_VIEW, PERMISSIONS.GIVING_SUMMARY]);
        await within(await screen.findByText('Given this month').then(() => card('Given this month'))).findByText('GHS 15,450.00');
        expect(screen.queryByText('Last service')).not.toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Attendance at recent services' })).not.toBeInTheDocument();
    });

    it('says plainly when no attendance has been recorded yet, and leads to the services', async () => {
        vi.mocked(reportsApi.attendance).mockResolvedValue({
            group_by: 'service',
            data: [],
            summary: { average: 0, highest: 0, lowest: 0 },
        });
        open();

        expect(
            await within(await screen.findByText('Last service').then(() => card('Last service'))).findByText('No attendance recorded yet'),
        ).toBeInTheDocument();
        expect(card('Last service')).toHaveAttribute('href', '/services');
        expect(screen.getAllByText('No attendance recorded yet')).toHaveLength(2);
    });

    it('keeps the other cards when one cannot be read, and offers to try that one again', async () => {
        vi.mocked(reportsApi.giving)
            .mockRejectedValueOnce(new ApiError('The server is busy.', 500))
            .mockResolvedValue(givingByMonth({ data: [givingByMonth().data[0]!] }));
        const { container } = open();

        expect(await screen.findByRole('alert')).toHaveTextContent('Given this month: The server is busy.');
        expect(await screen.findByText('212')).toBeInTheDocument();
        expect(within(card('Last service')).getByText('118')).toBeInTheDocument();
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

describe('DashboardPage: the card designs and charts', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary()], { total: 212 }));
        vi.mocked(reportsApi.attendance).mockResolvedValue(byService);
        vi.mocked(attendanceApi.absent).mockResolvedValue(pageOf([absentMember()], { total: 7 }));
        vi.mocked(activityApi.recent).mockResolvedValue(pageOf([activityLine()]));
        vi.mocked(reportsApi.giving).mockResolvedValue(givingByMonth());
    });

    it("leads with this month's giving as the one featured card, with the server's change against last month", async () => {
        open();

        const giving = await findCard('Given this month');
        expect(await within(giving).findByText('GHS 15,450.00')).toHaveClass('text-hero');
        expect(giving.querySelector('[data-variant="featured"]')).not.toBeNull();
        expect(within(giving).getByText('vs last month')).toBeInTheDocument();
        expect(giving.querySelector('[data-slot="stat-delta"]')).toHaveTextContent(/^(Up|Down) /);
        expect(document.querySelectorAll('[data-variant="featured"]')).toHaveLength(1);
    });

    it('shows the members and the absent members as icon cards, and the last service as a trend card', async () => {
        open();

        await screen.findByText('Active members');
        expect(card('Active members').querySelector('[data-variant="icon"]')).not.toBeNull();
        expect(card('Absent members').querySelector('[data-variant="icon"]')).not.toBeNull();
        expect(card('Last service').querySelector('[data-variant="trend"]')).not.toBeNull();
    });

    it('shows how the last service compares with the one before it, in people, and a trend over the recent services', async () => {
        open();

        const service = await findCard('Last service');
        expect(await within(service).findByText('+14')).toBeInTheDocument();
        expect(within(service).getByText('vs the service before')).toBeInTheDocument();
        expect(service.querySelector('[data-slot="stat-delta"]')).toHaveClass('text-feedback-success');
        expect(service.querySelector('[data-slot="stat-delta"]')).toHaveTextContent('Up +14');
        expect(within(service).getByRole('img', { name: 'Attendance at the last 2 services, from 104 to 118' })).toBeInTheDocument();
    });

    it('shows no change or trend where there is nothing to compare with', async () => {
        vi.mocked(reportsApi.attendance).mockResolvedValue({ group_by: 'service', data: [byService.data[0]!], summary: byService.summary });
        open();

        const service = await findCard('Last service');
        await within(service).findByText('118');
        expect(service.querySelector('[data-slot="stat-delta"]')).toBeNull();
        expect(within(service).queryByRole('img')).toBeNull();
    });

    it('charts the recent attendance and the giving by month, each with a table of the same figures', async () => {
        open();

        const attendance = (await screen.findByRole('heading', { name: 'Attendance at recent services' })).closest('figure') as HTMLElement;
        expect(within(attendance).getByText('People at each of the last 2 services, oldest on the left.')).toBeInTheDocument();
        const giving = (await screen.findByRole('heading', { name: 'Giving by month' })).closest('figure') as HTMLElement;
        expect(within(attendance).getByRole('button', { name: 'Table' })).toBeInTheDocument();
        expect(within(giving).getByRole('button', { name: 'Table' })).toBeInTheDocument();

        within(attendance).getByRole('button', { name: 'Table' }).click();
        expect(await within(attendance).findByRole('table')).toBeInTheDocument();
    });

    it('says "the last service", not "the last 1 services", when there is only one', async () => {
        vi.mocked(reportsApi.attendance).mockResolvedValue({ group_by: 'service', data: [byService.data[0]!], summary: byService.summary });
        open();

        expect(await screen.findByText('People at the last service.')).toBeInTheDocument();
        expect(screen.queryByText(/last 1 services/)).not.toBeInTheDocument();
    });

    it('asks for the attendance report once for both the card and the chart, and for the giving trend', async () => {
        open();
        await screen.findByRole('heading', { name: 'Attendance at recent services' });

        expect(reportsApi.attendance).toHaveBeenCalledTimes(1);
        expect(reportsApi.giving).toHaveBeenCalledWith({ months: 6, fundId: '' });
    });

    it('says plainly in the chart place when nothing has been recorded or given', async () => {
        vi.mocked(reportsApi.attendance).mockResolvedValue({
            group_by: 'service',
            data: [],
            summary: { average: 0, highest: 0, lowest: 0 },
        });
        vi.mocked(reportsApi.giving).mockResolvedValue(
            givingByMonth({ data: [{ ...givingByMonth().data[0]!, by_fund: [], total: '0.00', total_formatted: 'GHS 0.00' }] }),
        );
        open();

        expect(await screen.findByText('No giving recorded')).toBeInTheDocument();
        expect(screen.getAllByText('No attendance recorded yet')).toHaveLength(2);
    });

    it('draws the giving card without its trend when the six months cannot be had', async () => {
        vi.mocked(reportsApi.giving).mockImplementation(async (filters) => {
            if (filters.months === 6) {
                throw new ApiError('The server is busy.', 500);
            }

            return givingByMonth({ data: [givingByMonth().data[0]!] });
        });
        open();

        const giving = await findCard('Given this month');
        expect(await within(giving).findByText('GHS 15,450.00')).toBeInTheDocument();
        expect(within(giving).queryByRole('img')).toBeNull();
    });

    it('shows the chart cards only to those who may read what they hold', async () => {
        open([PERMISSIONS.MEMBERS_VIEW]);

        await screen.findByText('212');
        expect(screen.queryByRole('heading', { name: 'Attendance at recent services' })).not.toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Giving by month' })).not.toBeInTheDocument();
    });
});

describe('DashboardPage: recent activity', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(membersApi.list).mockResolvedValue(pageOf([memberSummary()], { total: 212 }));
        vi.mocked(reportsApi.attendance).mockResolvedValue(byService);
        vi.mocked(reportsApi.giving).mockResolvedValue(givingByMonth({ data: [givingByMonth().data[0]!] }));
        vi.mocked(attendanceApi.absent).mockResolvedValue(pageOf([], { total: 0 }));
        vi.mocked(activityApi.recent).mockResolvedValue(
            pageOf([
                activityLine(),
                activityLine({
                    id: 'activity-2',
                    kind: 'gift_recorded',
                    text: 'A gift of GHS 1,250.50 to Building Fund was recorded.',
                    actor_name: null,
                    occurred_at: null,
                }),
            ]),
        );
    });

    it('shows the sentences the server wrote, with who did it and when', async () => {
        open();

        const first = (await screen.findByText('Kwame Boateng was added as a member.')).closest('li') as HTMLElement;
        expect(within(first).getByText('Abena Pastor · 9 Nov 2026, 10:00')).toBeInTheDocument();
        expect(screen.getByRole('heading', { level: 2, name: 'Recent activity' })).toBeInTheDocument();
    });

    it('shows a line with no actor or time as just its sentence', async () => {
        open();

        const second = (await screen.findByText('A gift of GHS 1,250.50 to Building Fund was recorded.')).closest('li') as HTMLElement;
        expect(second.querySelectorAll('span.block')).toHaveLength(2);
        expect(within(second).queryByText(/·/)).not.toBeInTheDocument();
    });

    it('asks for the latest ten lines only', async () => {
        open();
        await screen.findByText('Kwame Boateng was added as a member.');

        expect(activityApi.recent).toHaveBeenCalledWith(10);
    });

    it('says plainly when nothing has happened yet', async () => {
        vi.mocked(activityApi.recent).mockResolvedValue(pageOf([]));
        open();

        expect(await screen.findByText('Nothing yet')).toBeInTheDocument();
    });

    it('keeps the cards when the feed cannot be read, and tries again', async () => {
        vi.mocked(activityApi.recent)
            .mockRejectedValueOnce(new ApiError('The server is busy.', 500))
            .mockResolvedValue(pageOf([activityLine()]));
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        expect(await screen.findByText('212')).toBeInTheDocument();

        screen.getByRole('button', { name: 'Try again' }).click();
        expect(await screen.findByText('Kwame Boateng was added as a member.')).toBeInTheDocument();
    });

    it('is not shown, and not asked for, to someone who may not read reports', async () => {
        open([PERMISSIONS.MEMBERS_VIEW]);

        await screen.findByText('212');
        expect(screen.queryByText('Recent activity')).not.toBeInTheDocument();
        expect(activityApi.recent).not.toHaveBeenCalled();
    });
});
