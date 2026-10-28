import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { SearchBox } from '@/components/ui/SearchBox';
import { useAttendanceMutations } from '@/hooks/useServiceAttendance';
import { useMembers } from '@/hooks/useMembers';

interface MemberCheckInPickerProps {
    serviceId: string;
}

/**
 * Marks members present by hand: search by name, phone or number, tick the people who are here, and send them all at
 * once. Ticks are kept while searching again, so a row at the door can be marked in a few searches.
 */
export function MemberCheckInPicker({ serviceId }: MemberCheckInPickerProps) {
    const [search, setSearch] = useState('');
    const [ticked, setTicked] = useState<Map<string, string>>(new Map());
    const { record } = useAttendanceMutations(serviceId);
    const members = useMembers({ search, status: '', gender: '', department_id: '', page: 1 });
    const results = search === '' ? [] : (members.data?.data ?? []);

    function toggle(id: string, name: string) {
        setTicked((current) => {
            const next = new Map(current);
            if (next.has(id)) next.delete(id);
            else next.set(id, name);

            return next;
        });
    }

    function send() {
        record.mutate([...ticked.keys()], {
            onSuccess: (result) => {
                toast.success(
                    `${result.recorded} checked in${result.already_checked_in > 0 ? `, ${result.already_checked_in} already in` : ''}.`,
                );
                result.errors.forEach((problem) => toast.error(problem.message));
                setTicked(new Map());
            },
            onError: (error) => toast.error(error.message),
        });
    }

    return (
        <section aria-labelledby="manual-heading" className="flex flex-col gap-3 rounded-xl border p-4">
            <h2 id="manual-heading" className="font-medium">
                Mark people present
            </h2>
            <SearchBox
                id="checkin-search"
                label="Find a member"
                placeholder="Name, phone or member number"
                value={search}
                onSearch={setSearch}
            />

            {search !== '' && members.isPending && (
                <p aria-busy="true" className="text-sm text-muted-foreground">
                    Searching…
                </p>
            )}
            {search !== '' && members.data && results.length === 0 && <p className="text-sm text-muted-foreground">No member found.</p>}

            {results.length > 0 && (
                <ul className="flex flex-col gap-1">
                    {results.map((member) => (
                        <li key={member.id} className="flex items-center gap-2">
                            <input
                                id={`tick-${member.id}`}
                                type="checkbox"
                                className="size-5 accent-primary"
                                checked={ticked.has(member.id)}
                                onChange={() => toggle(member.id, member.full_name)}
                            />
                            <label htmlFor={`tick-${member.id}`} className="text-sm">
                                {member.full_name} <span className="text-muted-foreground">{member.member_number}</span>
                            </label>
                        </li>
                    ))}
                </ul>
            )}

            {ticked.size > 0 && (
                <div className="flex flex-col gap-2">
                    <p className="text-sm text-muted-foreground">Ticked: {[...ticked.values()].join(', ')}</p>
                    <Button size="lg" onClick={send} disabled={record.isPending}>
                        {record.isPending ? 'Saving…' : `Mark ${ticked.size} present`}
                    </Button>
                </div>
            )}
        </section>
    );
}
