import { describe, expect, it } from 'vitest';
import { blankBroadcastValues, broadcastFormSchema, toBroadcastInput } from '@/components/Messages/broadcastFormSchema';

function problems(overrides: Record<string, string>): string[] {
    const result = broadcastFormSchema.safeParse({ ...blankBroadcastValues(), message: 'Hello', ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('broadcastFormSchema', () => {
    it('accepts a message for all active members', () => {
        expect(problems({})).toEqual([]);
    });

    it('needs a message that is not only spaces', () => {
        expect(problems({ message: '   ' })).toEqual(['message']);
    });

    it('only knows the member statuses', () => {
        expect(problems({ status: 'visitor' })).toEqual(['status']);
        expect(problems({ status: 'inactive' })).toEqual([]);
    });
});

describe('toBroadcastInput', () => {
    it('sends a text message to all active members by default, trimmed', () => {
        expect(toBroadcastInput({ ...blankBroadcastValues(), message: '  Hello  ' })).toEqual({
            channel: 'sms',
            message: 'Hello',
            recipient_filter: { status: 'active' },
        });
    });

    it('adds the department only when one is chosen', () => {
        expect(toBroadcastInput({ message: 'Hi', status: 'active', department_id: 'dept-1' }).recipient_filter).toEqual({
            status: 'active',
            department_id: 'dept-1',
        });
    });
});
