import { StatusBadge } from '@/components/ui/StatusBadge';
import { MESSAGE_STATUS_TONES, MESSAGE_TYPES } from '@/constants/messages';
import type { MessageLogLine } from '@/types/messages';
import { formatDateTime } from '@/utils/formatDateTime';

/** The words for a kind of message, or the kind as the server sent it when this app does not know it. */
function kindLabel(kind: string): string {
    return MESSAGE_TYPES.find((option) => option.value === kind)?.label ?? kind;
}

/**
 * One line of the message log: who it went to, what it said, how it was sent, when, and whether it got there. The
 * word for the status is shown beside its colour.
 */
export function MessageListItem({ message }: { message: MessageLogLine }) {
    const recipient = message.recipient_phone ?? message.recipient_email ?? 'Unknown recipient';
    const when = message.sent_at ?? message.created_at;

    return (
        <li className="flex flex-wrap items-start justify-between gap-3 rounded-xl border p-3">
            <span className="min-w-0 flex-1">
                <span className="block font-medium">{recipient}</span>
                <span className="block text-sm break-words">{message.content}</span>
                <span className="block text-sm text-muted-foreground">
                    {message.channel_label} · {kindLabel(message.message_type)}
                    {when ? ` · ${formatDateTime(when)}` : ''}
                </span>
            </span>
            <StatusBadge label={message.status_label} tone={MESSAGE_STATUS_TONES[message.status]} />
        </li>
    );
}
