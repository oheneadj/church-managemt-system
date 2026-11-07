/**
 * Shapes of the message log endpoint (docs/08-api.md, "Get Message Log").
 */
import type { MessageStatus } from '@/constants/enums';

/** One line of the message log: a text, WhatsApp message or email the platform sent for the church. */
export interface MessageLogLine {
    id: string;
    channel: string;
    channel_label: string;
    recipient_type: string;
    recipient_id: string | null;
    recipient_phone: string | null;
    recipient_email: string | null;
    message_type: string;
    content: string;
    status: MessageStatus;
    status_label: string;
    cost_units: string | null;
    sent_at: string | null;
    created_at: string | null;
}

/** What the message log is narrowed by. An empty text means no filter. */
export interface MessageFilters {
    channel: string;
    type: string;
    from: string;
    to: string;
    page: number;
}
