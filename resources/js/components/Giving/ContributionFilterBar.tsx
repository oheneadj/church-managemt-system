import { DateFilter } from '@/components/form/DateFilter';
import { SelectField } from '@/components/form/SelectField';
import { Button } from '@/components/ui/button';
import { PAYMENT_METHODS } from '@/constants/enums';
import { useFundTypes } from '@/hooks/useFundTypes';
import type { ContributionFilters } from '@/types/giving';

interface ContributionFilterBarProps {
    filters: ContributionFilters;
    onChange: (name: 'from' | 'to' | 'fund' | 'method' | 'voided', value: string) => void;
    onClear: () => void;
}

/**
 * The gift list's filters: the dates the giving happened between, the fund, how it was given, and whether to show voided
 * gifts instead. The dates are typed day first.
 */
export function ContributionFilterBar({ filters, onChange, onClear }: ContributionFilterBarProps) {
    const funds = useFundTypes();
    const fundOptions = (funds.data ?? []).map((fund) => ({ value: fund.id, label: fund.name }));
    const active = filters.from !== '' || filters.to !== '' || filters.fund !== '' || filters.method !== '' || filters.voided;

    return (
        <section aria-label="Filters" className="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
            <DateFilter id="gift-from" label="From" value={filters.from} onChange={(iso) => onChange('from', iso)} />
            <DateFilter id="gift-to" label="To" value={filters.to} onChange={(iso) => onChange('to', iso)} />
            <SelectField
                id="gift-fund"
                label="Fund"
                options={fundOptions}
                emptyLabel="All funds"
                value={filters.fund}
                onChange={(event) => onChange('fund', event.target.value)}
            />
            <SelectField
                id="gift-method"
                label="How it was given"
                options={PAYMENT_METHODS}
                emptyLabel="Any way"
                value={filters.method}
                onChange={(event) => onChange('method', event.target.value)}
            />
            <div className="flex items-center gap-4 sm:col-span-2">
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        className="size-5 accent-primary"
                        checked={filters.voided}
                        onChange={(event) => onChange('voided', event.target.checked ? '1' : '')}
                    />
                    Show only voided gifts
                </label>
                {active && (
                    <Button type="button" variant="outline" size="sm" onClick={onClear}>
                        Clear filters
                    </Button>
                )}
            </div>
        </section>
    );
}
