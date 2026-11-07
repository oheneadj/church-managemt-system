/**
 * The kinds of message a church sees in its message log, with the words shown for each. The server sends the kind as a
 * plain text (`message_type`); a kind this list does not know is shown as it came, so a new kind never breaks the page.
 * Codes for signing in are never in the log (config/messages.php, `hidden_types`).
 */
import type { Option } from '@/constants/enums';
import type { StatusTone } from '@/components/ui/StatusBadge';

export const MESSAGE_TYPES = [
    { value: 'broadcast', label: 'Broadcast' },
    { value: 'contribution_receipt', label: 'Gift receipt' },
    { value: 'attendance_alert', label: 'Absence text' },
    { value: 'checkin_reply', label: 'Check-in reply' },
] as const satisfies readonly Option<string>[];

/** The colour of each delivery status. The word is always shown beside it. */
export const MESSAGE_STATUS_TONES = {
    queued: 'neutral',
    sent: 'success',
    delivered: 'success',
    failed: 'danger',
} as const satisfies Record<string, StatusTone>;
