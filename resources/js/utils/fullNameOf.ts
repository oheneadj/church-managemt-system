import type { Member } from '@/types/members';

/**
 * The member's whole name in order: first, middle (if any) and last.
 */
export function fullNameOf(member: Pick<Member, 'first_name' | 'middle_name' | 'last_name'>): string {
    return [member.first_name, member.middle_name, member.last_name].filter(Boolean).join(' ');
}
