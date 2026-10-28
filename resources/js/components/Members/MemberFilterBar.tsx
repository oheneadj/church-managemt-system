import { SelectField } from '@/components/form/SelectField';
import { Button } from '@/components/ui/button';
import { SearchBox } from '@/components/ui/SearchBox';
import { GENDERS, MEMBER_STATUSES } from '@/constants/enums';
import { useDepartments } from '@/hooks/useDepartments';
import type { MemberFilters } from '@/types/members';

interface MemberFilterBarProps {
    filters: MemberFilters;
    onChange: (name: 'search' | 'status' | 'gender' | 'department_id', value: string) => void;
    onClear: () => void;
}

/**
 * The search box and the three drop-down filters above the directory. Searching happens when the user
 * presses Search or Enter, which is gentler on a slow connection than searching on every key press.
 */
export function MemberFilterBar({ filters, onChange, onClear }: MemberFilterBarProps) {
    const departments = useDepartments();
    const departmentOptions = (departments.data?.data ?? []).map((department) => ({ value: department.id, label: department.name }));
    const hasFilters = filters.search !== '' || filters.status !== '' || filters.gender !== '' || filters.department_id !== '';

    return (
        <section aria-label="Find members" className="flex flex-col gap-3">
            <SearchBox
                id="member-search"
                label="Search members"
                placeholder="Name, phone or member number"
                value={filters.search}
                onSearch={(text) => onChange('search', text)}
            />
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <SelectField
                    id="filter-status"
                    label="Status"
                    options={MEMBER_STATUSES}
                    emptyLabel="Active and prospects"
                    value={filters.status}
                    onChange={(event) => onChange('status', event.target.value)}
                />
                <SelectField
                    id="filter-gender"
                    label="Gender"
                    options={GENDERS}
                    emptyLabel="Any"
                    value={filters.gender}
                    onChange={(event) => onChange('gender', event.target.value)}
                />
                <SelectField
                    id="filter-department"
                    label="Department"
                    options={departmentOptions}
                    emptyLabel="Any"
                    value={filters.department_id}
                    onChange={(event) => onChange('department_id', event.target.value)}
                />
            </div>
            {hasFilters && (
                <div>
                    <Button variant="ghost" size="sm" onClick={onClear}>
                        Clear filters
                    </Button>
                </div>
            )}
        </section>
    );
}
