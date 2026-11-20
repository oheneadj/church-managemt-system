import { DateFilter } from '@/components/form/DateFilter';
import { SelectField } from '@/components/form/SelectField';
import { Button } from '@/components/ui/button';
import { MESSAGE_CHANNELS } from '@/constants/enums';
import { MESSAGE_TYPES } from '@/constants/messages';
import type { MessageFilters } from '@/types/messages';

interface MessageFilterBarProps {
    filters: MessageFilters;
    onChange: (name: 'channel' | 'type' | 'from' | 'to', value: string) => void;
    onClear: () => void;
}

/** The message log's filters: how it was sent, what kind it was, and the days it was sent between. */
export function MessageFilterBar({ filters, onChange, onClear }: MessageFilterBarProps) {
    const active = filters.channel !== '' || filters.type !== '' || filters.from !== '' || filters.to !== '';

    return (
        <section aria-label="Filters" className="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2">
            <SelectField
                id="message-channel"
                label="Sent by"
                options={MESSAGE_CHANNELS}
                emptyLabel="Any way"
                value={filters.channel}
                onChange={(event) => onChange('channel', event.target.value)}
            />
            <SelectField
                id="message-type"
                label="Kind"
                options={MESSAGE_TYPES}
                emptyLabel="Any kind"
                value={filters.type}
                onChange={(event) => onChange('type', event.target.value)}
            />
            <DateFilter id="message-from" label="From" value={filters.from} onChange={(iso) => onChange('from', iso)} />
            <DateFilter id="message-to" label="To" value={filters.to} onChange={(iso) => onChange('to', iso)} />
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
