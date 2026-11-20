import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { SearchBox } from '@/components/ui/SearchBox';
import { useMembers } from '@/hooks/useMembers';
import type { MemberSummary } from '@/types/members';

interface MemberPickerProps {
    id: string;
    label: string;
    /** The member chosen so far, if any. */
    selected: MemberSummary | undefined;
    onSelect: (member: MemberSummary | undefined) => void;
}

/**
 * Chooses one member by searching their name, phone or member number. Nothing is asked of the server until something is
 * typed. Once a member is chosen the box shows them with a Change button. It holds its own search box, so it must sit
 * outside any `<form>` (a search box is a form, and forms cannot nest).
 */
export function MemberPicker({ id, label, selected, onSelect }: MemberPickerProps) {
    const [search, setSearch] = useState('');
    const members = useMembers({ search, status: '', gender: '', department_id: '', page: 1 }, search !== '');

    if (selected !== undefined) {
        return (
            <div className="flex items-center justify-between gap-3 rounded-lg border px-3 py-2">
                <p className="text-sm">
                    <span className="font-medium">{selected.full_name}</span>{' '}
                    <span className="text-muted-foreground">{selected.member_number}</span>
                </p>
                <Button type="button" variant="outline" size="sm" onClick={() => onSelect(undefined)}>
                    Change
                </Button>
            </div>
        );
    }

    const results = search === '' ? [] : (members.data?.data ?? []);

    return (
        <div className="flex flex-col gap-2">
            <SearchBox id={id} label={label} placeholder="Name, phone or number" value={search} onSearch={setSearch} />

            {search !== '' && members.isPending && (
                <p aria-busy="true" className="text-sm text-muted-foreground">
                    Searching…
                </p>
            )}
            {search !== '' && members.isError && <p className="text-sm text-destructive">{members.error.message}</p>}
            {search !== '' && members.data && results.length === 0 && <p className="text-sm text-muted-foreground">No member found.</p>}

            {results.length > 0 && (
                <ul className="flex max-h-48 flex-col gap-1 overflow-y-auto">
                    {results.map((member) => (
                        <li key={member.id} className="flex items-center justify-between gap-2 text-sm">
                            <span>
                                {member.full_name} <span className="text-muted-foreground">{member.member_number}</span>
                            </span>
                            <Button type="button" variant="outline" size="sm" onClick={() => onSelect(member)}>
                                Choose
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
