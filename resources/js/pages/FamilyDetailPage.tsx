import { UsersRound } from 'lucide-react';
import { useState } from 'react';
import { Link, useParams } from 'react-router';
import { FamilyFormDialog } from '@/components/Families/FamilyFormDialog';
import { FamilyMemberList } from '@/components/Families/FamilyMemberList';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useFamily } from '@/hooks/useFamilies';
import { ApiError } from '@/services/ApiError';

/**
 * One family: its name and notes, and its members with their roles. Members are put in a family, and given a role in it,
 * from the member's own add or edit screen.
 */
export function FamilyDetailPage() {
    const { id = '' } = useParams();
    const family = useFamily(id);
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const [editing, setEditing] = useState(false);

    if (family.isPending) {
        return <Skeleton aria-busy="true" className="mx-auto h-40 max-w-3xl rounded-xl" />;
    }

    if (family.isError) {
        const refused = family.error instanceof ApiError && (family.error.status === 404 || family.error.status === 403);

        return (
            <div className="mx-auto flex max-w-3xl flex-col gap-3">
                <ErrorNotice
                    message={refused ? 'This family was not found, or you do not have access to it.' : family.error.message}
                    onRetry={refused ? undefined : () => void family.refetch()}
                />
                <Link to={ROUTES.FAMILIES} className="text-sm underline underline-offset-4">
                    Back to families
                </Link>
            </div>
        );
    }

    const record = family.data;
    const members = record.members ?? [];

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-4">
            <Link to={ROUTES.FAMILIES} className="text-sm underline underline-offset-4">
                ← All families
            </Link>
            <PageHeader
                title={record.name}
                description={record.notes ?? undefined}
                actions={
                    canEdit && (
                        <Button variant="outline" size="lg" onClick={() => setEditing(true)}>
                            Edit
                        </Button>
                    )
                }
            />
            {members.length === 0 ? (
                <EmptyState
                    icon={UsersRound}
                    title="No members to show"
                    description="Open a member, choose Edit, and pick this family to add them."
                />
            ) : (
                <section aria-label="Members of this family">
                    <FamilyMemberList members={members} />
                </section>
            )}
            {canEdit && <FamilyFormDialog family={record} open={editing} onOpenChange={setEditing} />}
        </div>
    );
}
