import type { SyntheticEvent } from 'react';
import { SelectField } from '@/components/form/SelectField';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

    function search(event: SyntheticEvent<HTMLFormElement>) {
        event.preventDefault();
        const text = new FormData(event.currentTarget).get('search');
        onChange('search', typeof text === 'string' ? text.trim() : '');
    }

    return (
        <section aria-label="Find members" className="flex flex-col gap-3">
            <form onSubmit={search} role="search" className="flex items-end gap-2">
                <div className="flex flex-1 flex-col gap-1.5">
                    <Label htmlFor="member-search">Search members</Label>
                    {/* The key restarts the box when the URL changes, for example after Clear. */}
                    <Input
                        key={filters.search}
                        id="member-search"
                        name="search"
                        type="search"
                        className="h-10"
                        placeholder="Name, phone or member number"
                        defaultValue={filters.search}
                    />
                </div>
                <Button type="submit" size="lg" className="h-10">
                    Search
                </Button>
            </form>
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
