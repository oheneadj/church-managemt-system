import type { SyntheticEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface SearchBoxProps {
    id: string;
    label: string;
    placeholder?: string;
    /** What is searched for now. The box restarts with this value when it changes, for example after Clear. */
    value: string;
    onSearch: (text: string) => void;
}

/**
 * A search box that searches when the user presses Search or Enter, which is gentler on a slow connection than
 * searching on every key press.
 */
export function SearchBox({ id, label, placeholder, value, onSearch }: SearchBoxProps) {
    function submit(event: SyntheticEvent<HTMLFormElement>) {
        event.preventDefault();
        const text = new FormData(event.currentTarget).get('search');
        onSearch(typeof text === 'string' ? text.trim() : '');
    }

    return (
        <form onSubmit={submit} role="search" className="flex items-end gap-2">
            <div className="flex flex-1 flex-col gap-1.5">
                <Label htmlFor={id}>{label}</Label>
                <Input key={value} id={id} name="search" type="search" className="h-10" placeholder={placeholder} defaultValue={value} />
            </div>
            <Button type="submit" size="lg" className="h-10">
                Search
            </Button>
        </form>
    );
}
