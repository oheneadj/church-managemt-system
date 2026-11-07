import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { ReportsPage } from '@/pages/ReportsPage';
import { reportsApi } from '@/services/api/reports';
import { ApiError } from '@/services/ApiError';
import { attendanceByWeek } from '@/test/fixtures';
import { renderPage } from '@/test/render';

vi.mock('@/services/api/reports');

function open(url: string = ROUTES.REPORTS) {
    return renderPage(<ReportsPage />, { path: ROUTES.REPORTS, url, permissions: [PERMISSIONS.REPORTS_VIEW] });
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
