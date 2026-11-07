import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS, type Permission } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { ReportsPage } from '@/pages/ReportsPage';
import { fundTypesApi } from '@/services/api/fundTypes';
import { reportsApi } from '@/services/api/reports';
import { ApiError } from '@/services/ApiError';
import { attendanceByWeek, fundType, givingByMonth } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/reports');
vi.mock('@/services/api/fundTypes');

function open(url: string = ROUTES.REPORTS, permissions: Permission[] = [PERMISSIONS.REPORTS_VIEW]) {
    return renderPage(<ReportsPage />, { path: ROUTES.REPORTS, url, permissions });
}

describe('ReportsPage: attendance', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(reportsApi.attendance).mockResolvedValue(attendanceByWeek());
    });

    it('shows the average, highest and lowest, and a column for each week', async () => {
        open();

        const average = (await screen.findByText('Average')).closest('div') as HTMLElement;
        expect(within(average).getByText('111')).toBeInTheDocument();
        expect(within(screen.getByText('Highest').closest('div') as HTMLElement).getByText('124')).toBeInTheDocument();
        expect(within(screen.getByText('Lowest').closest('div') as HTMLElement).getByText('98')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Week of 12 Oct 2026: 124 people' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Week of 19 Oct 2026: 0 people' })).toBeInTheDocument();
        expect(reportsApi.attendance).toHaveBeenCalledWith({ weeks: 12, groupBy: 'week' });
    });

    it('asks again when the weeks or the grouping change, and keeps the choice in the address', async () => {
        const user = userEvent.setup();
        const { router } = open();
        await screen.findByText('Average');

        await user.selectOptions(screen.getByLabelText('Show'), '26');
        await waitFor(() => expect(reportsApi.attendance).toHaveBeenLastCalledWith({ weeks: 26, groupBy: 'week' }));

        await user.selectOptions(screen.getByLabelText('Group'), 'service');
        await waitFor(() => expect(reportsApi.attendance).toHaveBeenLastCalledWith({ weeks: 26, groupBy: 'service' }));
        expect(router.state.location.search).toContain('weeks=26');
        expect(router.state.location.search).toContain('group=service');
    });

    it('ignores choices in the address that are not real ones', async () => {
        open('/reports?weeks=500&group=moon');

        await screen.findByText('Average');
        expect(reportsApi.attendance).toHaveBeenCalledWith({ weeks: 12, groupBy: 'week' });
    });

    it('swaps the chart for a table of the same figures, and back', async () => {
        const user = userEvent.setup();
        open();
        await screen.findByText('Average');

        await user.click(screen.getByRole('button', { name: 'Show as table' }));

        const table = screen.getByRole('table', { name: 'Attendance, newest first' });
        expect(within(table).getByRole('row', { name: /12 Oct 2026 124 3 2/ })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Week of 12 Oct 2026: 124 people' })).not.toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Show as chart' }));
        expect(screen.getByRole('button', { name: 'Week of 12 Oct 2026: 124 people' })).toBeInTheDocument();
    });

    it('lists services with their names when grouped by service', async () => {
        vi.mocked(reportsApi.attendance).mockResolvedValue({
            group_by: 'service',
            data: [{ service_id: 's1', service_date: '2026-10-18', service_name: 'Sunday Morning Service', total: 118 }],
            summary: { average: 118, highest: 118, lowest: 118 },
        });
        const user = userEvent.setup();
        open('/reports?group=service');
        await screen.findByText('Average');

        expect(screen.getByRole('button', { name: 'Sunday Morning Service, 18 Oct 2026: 118 people' })).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Show as table' }));
        expect(screen.getByRole('row', { name: /18 Oct 2026 Sunday Morning Service 118/ })).toBeInTheDocument();
    });

    it('says plainly when nothing was recorded, instead of drawing an empty chart', async () => {
        vi.mocked(reportsApi.attendance).mockResolvedValue(attendanceByWeek({ data: [], summary: { average: 0, highest: 0, lowest: 0 } }));
        open();

        expect(await screen.findByText('No attendance recorded')).toBeInTheDocument();
        expect(screen.queryByText('Average')).not.toBeInTheDocument();
    });

    it('says what went wrong and tries again', async () => {
        vi.mocked(reportsApi.attendance)
            .mockRejectedValueOnce(new ApiError('The server is busy.', 500))
            .mockResolvedValue(attendanceByWeek());
        const user = userEvent.setup();
        open();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByText('Average')).toBeInTheDocument();
    });
});

describe('ReportsPage: giving', () => {
    const givingTab = '/reports?tab=giving';

    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(reportsApi.giving).mockResolvedValue(givingByMonth());
        vi.mocked(fundTypesApi.active).mockResolvedValue([
            fundType({ id: 'fund-1', name: 'Tithe' }),
            fundType({ id: 'fund-2', name: 'Sunday Offering' }),
            fundType({ id: 'fund-3', name: 'Building Fund' }),
        ]);
    });

    it('shows the period against the months before, and a stacked column for each month with a legend of the funds', async () => {
        open(givingTab, [PERMISSIONS.GIVING_SUMMARY]);

        expect(await screen.findByText('GHS 18,450.00')).toBeInTheDocument();
        expect(screen.getByText('GHS 16,000.00')).toBeInTheDocument();
        expect(screen.getByText('+15.3%')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'October 2026: GHS 15,450.00' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'August 2026: GHS 0.00' })).toBeInTheDocument();
        const legend = screen.getByRole('list', { name: 'Legend' });
        expect(within(legend).getByText('Tithe')).toBeInTheDocument();
        expect(within(legend).getByText('Building Fund')).toBeInTheDocument();
        expect(within(legend).queryByText('Sunday Offering')).not.toBeInTheDocument();
        expect(reportsApi.giving).toHaveBeenCalledWith({ months: 12, fundId: '' });
    });

    it('says there is no change to show when nothing was given in the months before', async () => {
        vi.mocked(reportsApi.giving).mockResolvedValue(
            givingByMonth({
                summary: { ...givingByMonth().summary, change_percent: null, previous_total: '0.00', previous_total_formatted: 'GHS 0.00' },
            }),
        );
        open(givingTab, [PERMISSIONS.GIVING_SUMMARY]);

        expect(await screen.findByText('Nothing was given in the months before')).toBeInTheDocument();
        expect(screen.getByText('–')).toBeInTheDocument();
    });

    it('asks again for another period or one fund, and keeps both in the address', async () => {
        const user = userEvent.setup();
        const { router } = open(givingTab, [PERMISSIONS.GIVING_SUMMARY]);
        await screen.findByText('GHS 18,450.00');

        await user.selectOptions(screen.getByLabelText('Show'), '6');
        await waitFor(() => expect(reportsApi.giving).toHaveBeenLastCalledWith({ months: 6, fundId: '' }));

        await screen.findByRole('option', { name: 'Building Fund' });
        await user.selectOptions(screen.getByLabelText('Fund'), 'fund-3');
        expect(router.state.location.search).toContain('months=6');
    });

    it('ignores a fund in the address that is not a real id', async () => {
        open('/reports?tab=giving&fund=not-an-id', [PERMISSIONS.GIVING_SUMMARY]);

        await screen.findByText('GHS 18,450.00');
        expect(reportsApi.giving).toHaveBeenCalledWith({ months: 12, fundId: '' });
    });

    it("swaps the chart for a table with a column for each fund and the server's own amounts", async () => {
        const user = userEvent.setup();
        open(givingTab, [PERMISSIONS.GIVING_SUMMARY]);
        await screen.findByText('GHS 18,450.00');

        await user.click(screen.getByRole('button', { name: 'Show as table' }));

        const table = screen.getByRole('table', { name: 'Giving by month, newest first' });
        expect(within(table).getByRole('row', { name: /October 2026 GHS 15,450.00 GHS 8,000.00 GHS 7,450.00/ })).toBeInTheDocument();
        expect(within(table).getByRole('row', { name: /September 2026 GHS 3,000.00 GHS 3,000.00 –/ })).toBeInTheDocument();
    });

    it('says plainly when nothing was given', async () => {
        vi.mocked(reportsApi.giving).mockResolvedValue(
            givingByMonth({
                data: [{ month: '2026-10', month_start: '2026-10-01', total: '0.00', total_formatted: 'GHS 0.00', by_fund: [] }],
            }),
        );
        open(givingTab, [PERMISSIONS.GIVING_SUMMARY]);

        expect(await screen.findByText('No giving recorded')).toBeInTheDocument();
    });

    it('says what went wrong and tries again', async () => {
        vi.mocked(reportsApi.giving).mockRejectedValueOnce(new ApiError('The server is busy.', 500)).mockResolvedValue(givingByMonth());
        const user = userEvent.setup();
        open(givingTab, [PERMISSIONS.GIVING_SUMMARY]);

        expect(await screen.findByRole('alert')).toHaveTextContent('The server is busy.');
        await user.click(screen.getByRole('button', { name: 'Try again' }));

        expect(await screen.findByText('GHS 18,450.00')).toBeInTheDocument();
    });
});

describe('ReportsPage: who sees which tab', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(reportsApi.attendance).mockResolvedValue(attendanceByWeek());
        vi.mocked(reportsApi.giving).mockResolvedValue(givingByMonth());
        vi.mocked(fundTypesApi.active).mockResolvedValue([]);
    });

    it('shows both tabs to someone who may read both', async () => {
        open('/reports', [PERMISSIONS.REPORTS_VIEW, PERMISSIONS.GIVING_SUMMARY]);

        expect(await screen.findByRole('tab', { name: 'Attendance' })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Giving' })).toBeInTheDocument();
    });

    it('hides the giving tab, and never asks for giving, from someone with only reports.view', async () => {
        open('/reports?tab=giving', [PERMISSIONS.REPORTS_VIEW]);

        await screen.findByText('Average');
        expect(screen.queryByRole('tab', { name: 'Giving' })).not.toBeInTheDocument();
        expect(reportsApi.giving).not.toHaveBeenCalled();
    });

    it('hides the attendance tab from someone who may read only giving, and opens on giving', async () => {
        open('/reports', [PERMISSIONS.GIVING_SUMMARY]);

        expect(await screen.findByText('GHS 18,450.00')).toBeInTheDocument();
        expect(screen.queryByRole('tab', { name: 'Attendance' })).not.toBeInTheDocument();
        expect(reportsApi.attendance).not.toHaveBeenCalled();
    });

    it('says so when someone may read no report', async () => {
        open('/reports', []);

        expect(await screen.findByText('No reports for you')).toBeInTheDocument();
    });
});
