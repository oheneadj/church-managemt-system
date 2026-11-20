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

/** Who a broadcast goes to: members of one status and, if given, one department. */
export interface BroadcastAudience {
    status: string;
    department_id?: string;
}

/** What the estimate and the send both take (docs/08, Send Broadcast). Only text messages are open for now. */
export interface BroadcastInput {
    channel: 'sms';
    message: string;
    recipient_filter: BroadcastAudience;
}

/** What a broadcast would reach and cost (docs/08, Estimate a Broadcast). Nothing is sent or charged to get it. */
export interface BroadcastEstimate {
    recipients: number;
    parts_per_message: number;
    credits_needed: number;
    credit_balance: number;
    enough_credits: boolean;
}

/** The answer to a send: the broadcast is queued and a worker texts the members. */
export interface BroadcastQueued {
    queued: boolean;
    broadcast_id: string;
    estimated_recipients: number;
    estimated_credit_cost: number;
    current_credit_balance: number;
}
