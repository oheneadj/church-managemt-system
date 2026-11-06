import { SelectField } from '@/components/form/SelectField';
import { Button } from '@/components/ui/button';
import { PLEDGE_STATUSES } from '@/constants/enums';
import { useFundTypes } from '@/hooks/useFundTypes';
import type { PledgeFilters } from '@/types/giving';

interface PledgeFilterBarProps {
    filters: PledgeFilters;
    onChange: (name: 'status' | 'fund', value: string) => void;
    onClear: () => void;
}

/** The pledge list's filters: where each pledge stands, and which fund it is for. */
export function PledgeFilterBar({ filters, onChange, onClear }: PledgeFilterBarProps) {
    const funds = useFundTypes();
    const fundOptions = (funds.data ?? []).map((fund) => ({ value: fund.id, label: fund.name }));
    const active = filters.status !== '' || filters.fund !== '';

    return (
        <section aria-label="Filters" className="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
            <SelectField
                id="pledge-filter-status"
                label="Status"
                options={PLEDGE_STATUSES}
                emptyLabel="Any status"
                value={filters.status}
                onChange={(event) => onChange('status', event.target.value)}
            />
            <SelectField
                id="pledge-filter-fund"
                label="Fund"
                options={fundOptions}
                emptyLabel="All funds"
                value={filters.fund}
                onChange={(event) => onChange('fund', event.target.value)}
            />
            {active && (
                <div className="sm:col-span-2">
                    <Button type="button" variant="outline" size="sm" onClick={onClear}>
                        Clear filters
                    </Button>
                </div>
            )}
        </section>
    );
}
