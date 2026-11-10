import { EyeIcon, PencilIcon } from 'lucide-react';
import { Link } from 'react-router';
import { MemberAvatar } from '@/components/Members/MemberAvatar';
import { MemberStatusBadge } from '@/components/Members/MemberStatusBadge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow, TableRowActions } from '@/components/ui/table';
import { ROUTES } from '@/constants/routes';
import type { MemberSummary } from '@/types/members';

interface MemberTableProps {
    members: readonly MemberSummary[];
    /** Whether the signed-in person may edit members: the Edit button is only drawn for them (the server still decides). */
    canEdit: boolean;
    /** True while newer figures load: the rows stay, dimmed, so nothing jumps. */
    refreshing?: boolean;
}

/**
 * The member directory as a table, for a wide screen (the shared table, docs/design-system/interactions.md, section 14). Each
 * row has the member's photo or initials, their name as the link to the profile, their number, phone, departments and status,
 * and View and Edit buttons named for the member. The photo is decorative because the name is written beside it. On a phone the
 * page draws the card list instead.
 */
export function MemberTable({ members, canEdit, refreshing = false }: MemberTableProps) {
    return (
        <Table refreshing={refreshing}>
            <TableCaption className="sr-only">Members</TableCaption>
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead>Member</TableHead>
                    <TableHead>Phone</TableHead>
                    <TableHead>Departments</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {members.map((member) => (
                    <TableRow key={member.id}>
                        <TableCell>
                            <div className="flex items-center gap-3">
                                <MemberAvatar fullName={member.full_name} photoUrl={member.photo_url} size="md" />
                                <div className="min-w-0">
                                    <Link
                                        to={ROUTES.MEMBER(member.id)}
                                        className="block truncate font-medium outline-none hover:underline focus-visible:ring-3 focus-visible:ring-ring"
                                    >
                                        {member.full_name}
                                    </Link>
                                    <span className="block text-body-sm text-muted-foreground">{member.member_number}</span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell className="whitespace-nowrap tabular-nums">{member.phone}</TableCell>
                        <TableCell className="max-w-56 truncate text-muted-foreground">
                            {member.departments.length > 0 ? member.departments.join(', ') : 'None'}
                        </TableCell>
                        <TableCell>
                            <MemberStatusBadge status={member.status} label={member.status_label} />
                        </TableCell>
                        <TableCell>
                            <TableRowActions>
                                <Button asChild variant="ghost" size="icon-sm">
                                    <Link to={ROUTES.MEMBER(member.id)} aria-label={`View ${member.full_name}`}>
                                        <EyeIcon />
                                    </Link>
                                </Button>
                                {canEdit && (
                                    <Button asChild variant="ghost" size="icon-sm">
                                        <Link to={ROUTES.MEMBER_EDIT(member.id)} aria-label={`Edit ${member.full_name}`}>
                                            <PencilIcon />
                                        </Link>
                                    </Button>
                                )}
                            </TableRowActions>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
