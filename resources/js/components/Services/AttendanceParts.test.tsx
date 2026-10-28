import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AttendanceList } from '@/components/Services/AttendanceList';
import { AttendanceSummaryCards } from '@/components/Services/AttendanceSummaryCards';
import { attendanceRecord, serviceAttendance } from '@/test/fixtures';
import { formatClock } from '@/utils/formatClock';

describe('AttendanceSummaryCards', () => {
    it('shows the headcount, members and visitors, the rate and only the methods that were used', () => {
        const { summary } = serviceAttendance();
        render(
            <AttendanceSummaryCards
                summary={{
                    ...summary,
                    total: 7,
                    members: 6,
                    visitors: 1,
                    attendance_rate: 60,
                    by_method: { manual: 5, whatsapp: 2, qr: 0, biometric: 0 },
                }}
            />,
        );

        const region = screen.getByRole('region', { name: 'Headcount' });
        expect(region).toHaveTextContent('7 checked in');
        expect(region).toHaveTextContent('6 members · 1 visitors · 60% of 10 active members');
        expect(region).toHaveTextContent('Manual: 5');
        expect(region).toHaveTextContent('WhatsApp: 2');
        expect(region).not.toHaveTextContent('QR code');
    });

    it('is announced politely to screen readers when the count changes', () => {
        render(<AttendanceSummaryCards summary={serviceAttendance().summary} />);

        expect(screen.getByRole('region', { name: 'Headcount' })).toHaveAttribute('aria-live', 'polite');
    });
});

describe('AttendanceList', () => {
    it('shows a member with their number, how and when they arrived', () => {
        render(
            <AttendanceList
                records={[attendanceRecord({ method: 'whatsapp', method_label: 'WhatsApp', checked_in_at: '2026-10-25T09:05:00+00:00' })]}
            />,
        );

        expect(screen.getByText('Emmanuel Mensah-Boateng')).toBeInTheDocument();
        expect(screen.getByText('GRC-0001')).toBeInTheDocument();
        expect(screen.getByText('WhatsApp')).toBeInTheDocument();
        expect(screen.getByText('09:05')).toBeInTheDocument();
    });

    it('shows a visitor by name and marks them as a visitor', () => {
        render(<AttendanceList records={[attendanceRecord({ member: null, visitor_name: 'Yaw Boateng' })]} />);

        expect(screen.getByText('Yaw Boateng')).toBeInTheDocument();
        expect(screen.getByText('Visitor')).toBeInTheDocument();
    });

    it('says so when nobody has checked in', () => {
        render(<AttendanceList records={[]} />);

        expect(screen.getByText('Nobody has checked in yet')).toBeInTheDocument();
    });
});

describe('formatClock', () => {
    it('shows the time of day of a UTC timestamp', () => {
        expect(formatClock('2026-10-25T23:59:00+00:00')).toBe('23:59');
        expect(formatClock('2026-10-25T08:05:00Z')).toBe('08:05');
    });
});
