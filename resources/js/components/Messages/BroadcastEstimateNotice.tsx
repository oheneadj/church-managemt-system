import type { UseQueryResult } from '@tanstack/react-query';
import { ApiError } from '@/services/ApiError';
import type { BroadcastEstimate } from '@/types/messages';
import { formatCount } from '@/utils/formatCount';

/** "1 member", "48 members". */
function membersText(count: number): string {
    return `${formatCount(count)} ${count === 1 ? 'member' : 'members'}`;
}

/** "1 SMS credit", "48 SMS credits". */
function creditsText(count: number): string {
    return `${formatCount(count)} SMS ${count === 1 ? 'credit' : 'credits'}`;
}

/** The server's reason a message cannot be costed (too long, say), beside the field when it names one. */
function reasonFrom(error: Error): string {
    return error instanceof ApiError ? (error.fieldErrors.message?.[0] ?? error.message) : error.message;
}

/**
 * Says who the broadcast would reach and what it would cost, from the estimate the server works out as the message is
 * typed (QA-08 TC-08-08). Nothing is sent or charged by looking. It warns, in words, when the church cannot pay or nobody
 * would be reached, and the page keeps the Send button off in both cases.
 */
export function BroadcastEstimateNotice({ estimate }: { estimate: UseQueryResult<BroadcastEstimate, Error> }) {
    if (estimate.isError) {
        return (
            <p role="alert" className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                {reasonFrom(estimate.error)}
            </p>
        );
    }

    if (estimate.data === undefined) {
        return <p className="text-sm text-muted-foreground">Write your message to see who it reaches and what it costs.</p>;
    }

    const { recipients, parts_per_message: parts, credits_needed: needed, credit_balance: balance, enough_credits: enough } = estimate.data;

    if (recipients === 0) {
        return (
            <p role="status" className="rounded-lg border p-3 text-sm">
                Nobody matches: there is nobody to send this to. Members need a phone number and must not have turned texts off.
            </p>
        );
    }

    return (
        <div role="status" aria-live="polite" className="flex flex-col gap-1 rounded-lg border p-3 text-sm">
            <p>
                This message will be sent to {membersText(recipients)} and use about {creditsText(needed)}.
                {parts > 1 && ` Each text is ${parts} parts long.`}
            </p>
            <p className={enough ? 'text-muted-foreground' : 'font-medium text-destructive'}>
                {enough
                    ? `You have ${creditsText(balance)}.`
                    : `You have ${creditsText(balance)}, which is not enough. Top up your SMS credits or shorten the message.`}
            </p>
        </div>
    );
}
