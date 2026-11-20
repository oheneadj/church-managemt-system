import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { MemberAttendanceTab } from '@/components/Members/MemberAttendanceTab';
import { MemberGivingTab } from '@/components/Members/MemberGivingTab';
import { MilestoneList } from '@/components/Members/MilestoneList';
import { DetailList } from '@/components/ui/DetailList';

describe('MemberGivingTab', () => {
    it('shows the amounts exactly as formatted by the API', () => {
        render(
            <MemberGivingTab
                summary={{
                    this_year: '2400.00',
                    this_year_formatted: 'GHS 2,400.00',
                    this_month: '200.00',
                    this_month_formatted: 'GHS 200.00',
                    all_time: '8650.50',
                    all_time_formatted: 'GHS 8,650.50',
                }}
            />,
        );

        expect(screen.getByText('GHS 200.00')).toBeInTheDocument();
        expect(screen.getByText('GHS 2,400.00')).toBeInTheDocument();
        expect(screen.getByText('GHS 8,650.50')).toBeInTheDocument();
    });

    it('explains politely when the role cannot see giving', () => {
        render(<MemberGivingTab summary={undefined} />);

        expect(screen.getByText('Your role does not include giving details.')).toBeInTheDocument();
    });
});

describe('MemberAttendanceTab', () => {
    it('counts services over the last 4, 8 and 12 weeks', () => {
        render(<MemberAttendanceTab summary={{ last_4_weeks: 1, last_8_weeks: 7, last_12_weeks: 10 }} />);

        expect(screen.getByText('1 service')).toBeInTheDocument();
        expect(screen.getByText('7 services')).toBeInTheDocument();
        expect(screen.getByText('10 services')).toBeInTheDocument();
    });
});

describe('MilestoneList', () => {
    it('lists milestones oldest first with date and officiant', () => {
        render(
            <MilestoneList
                milestones={[
                    {
                        id: '2',
                        type: 'confirmation',
                        type_label: 'Confirmation',
                        occurred_at: '2020-03-01',
                        officiant: null,
                        cohort: 'Class 2020',
                        notes: null,
                    },
                    {
                        id: '1',
                        type: 'baptism',
                        type_label: 'Baptism',
                        occurred_at: '2010-06-05',
                        officiant: 'Rev. Acheampong',
                        cohort: null,
                        notes: null,
                    },
                ]}
            />,
        );

        const items = screen.getAllByRole('listitem');
        expect(items[0]).toHaveTextContent('Baptism · 5 Jun 2010 · Rev. Acheampong');
        expect(items[1]).toHaveTextContent('Confirmation · 1 Mar 2020 · Class 2020');
    });

    it('says so when there are none', () => {
        render(<MilestoneList milestones={[]} />);

        expect(screen.getByText('No milestones recorded yet.')).toBeInTheDocument();
    });
});

describe('DetailList', () => {
    it('shows a dash for a missing value', () => {
        render(
            <DetailList
                items={[
                    { label: 'Phone', value: '+233244123456' },
                    { label: 'Email', value: null },
                ]}
            />,
        );

        expect(screen.getByText('Phone')).toBeInTheDocument();
        expect(screen.getByText('—')).toBeInTheDocument();
    });
});
