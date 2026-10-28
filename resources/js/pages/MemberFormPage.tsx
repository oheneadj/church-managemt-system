import { Link, useParams } from 'react-router';
import { MemberForm } from '@/components/Members/MemberForm';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import { PERMISSIONS } from '@/constants/permissions';
import { ROUTES } from '@/constants/routes';
import { useCan } from '@/hooks/useCan';
import { useMember } from '@/hooks/useMembers';
import { ApiError } from '@/services/ApiError';
import { fullNameOf } from '@/utils/fullNameOf';

/**
 * The add member and edit member screen. With an id in the address it edits that member, otherwise it adds a new one.
 * Staff without the edit permission, and the records of deceased members, are shown a plain message instead.
 */
export function MemberFormPage() {
    const { id } = useParams();
    const canEdit = useCan(PERMISSIONS.MEMBERS_EDIT);
    const member = useMember(id ?? '');
    const editing = id !== undefined;

    if (!canEdit) {
        return <ErrorNotice message="Your role cannot add or change members." />;
    }

    if (editing && member.isPending) {
        return <Skeleton aria-busy="true" className="mx-auto h-64 max-w-2xl rounded-xl" />;
    }

    if (editing && member.isError) {
        const refused = member.error instanceof ApiError && (member.error.status === 404 || member.error.status === 403);

        return (
            <div className="mx-auto flex max-w-2xl flex-col gap-3">
                <ErrorNotice message={refused ? 'This member was not found, or you do not have access to them.' : member.error.message} />
                <Link to={ROUTES.MEMBERS} className="text-sm underline underline-offset-4">
                    Back to members
                </Link>
            </div>
        );
    }

    if (editing && member.data?.status === 'deceased') {
        return <ErrorNotice message="The record of a deceased member cannot be changed." />;
    }

    return (
        <div className="mx-auto flex max-w-2xl flex-col gap-4">
            <PageHeader title={editing && member.data ? `Edit ${fullNameOf(member.data)}` : 'Add member'} />
            <MemberForm key={member.data?.id ?? 'new'} member={editing ? member.data : undefined} />
        </div>
    );
}
