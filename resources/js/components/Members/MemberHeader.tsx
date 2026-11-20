import { Link } from 'react-router';
import { MemberAvatar } from '@/components/Members/MemberAvatar';
import { MemberStatusBadge } from '@/components/Members/MemberStatusBadge';
import { PhotoUploadDialog } from '@/components/Members/PhotoUploadDialog';
import { Button } from '@/components/ui/button';
import { ROUTES } from '@/constants/routes';
import type { Member } from '@/types/members';

interface MemberHeaderProps {
    member: Member;
    fullName: string;
    /** Whether the signed-in user may change this member (needs the edit permission, and the member must be alive). */
    editable: boolean;
    onChangeStatus: () => void;
}

/**
 * The top of the profile: photo, name, number, status, and the actions that change the member.
 */
export function MemberHeader({ member, fullName, editable, onChangeStatus }: MemberHeaderProps) {
    return (
        <header className="flex flex-col gap-6 rounded-xl border bg-card p-6 shadow-xs sm:flex-row sm:items-center">
            <div className="flex flex-col items-start gap-2">
                <MemberAvatar fullName={fullName} photoUrl={member.photo_url} size="2xl" />
                {editable && <PhotoUploadDialog memberId={member.id} />}
            </div>
            <div className="flex-1">
                <h1 className="text-h2 font-semibold md:text-h1">{fullName}</h1>
                <p className="text-body text-muted-foreground">{member.member_number}</p>
                <div className="mt-2">
                    <MemberStatusBadge status={member.status} label={member.status_label} />
                </div>
                {member.status_note && <p className="mt-1 text-body-sm text-muted-foreground">{member.status_note}</p>}
            </div>
            {editable && (
                <div className="flex flex-wrap gap-2">
                    <Button asChild>
                        <Link to={ROUTES.MEMBER_EDIT(member.id)}>Edit</Link>
                    </Button>
                    <Button type="button" variant="outline" onClick={onChangeStatus}>
                        Change status
                    </Button>
                </div>
            )}
        </header>
    );
}
