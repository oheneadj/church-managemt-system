import { Link } from 'react-router';
import { MemberAvatar } from '@/components/Members/MemberAvatar';
import { MemberStatusBadge } from '@/components/Members/MemberStatusBadge';
import { PhotoUploadButton } from '@/components/Members/PhotoUploadButton';
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
        <header className="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div className="flex flex-col items-start gap-2">
                <MemberAvatar fullName={fullName} photoUrl={member.photo_url} className="size-24" />
                {editable && <PhotoUploadButton memberId={member.id} />}
            </div>
            <div className="flex-1">
                <h1 className="text-xl font-semibold">{fullName}</h1>
                <p className="text-sm text-muted-foreground">{member.member_number}</p>
                <div className="mt-2">
                    <MemberStatusBadge status={member.status} label={member.status_label} />
                </div>
                {member.status_note && <p className="mt-1 text-sm text-muted-foreground">{member.status_note}</p>}
            </div>
            {editable && (
                <div className="flex flex-wrap gap-2">
                    <Button asChild size="lg">
                        <Link to={ROUTES.MEMBER_EDIT(member.id)}>Edit</Link>
                    </Button>
                    <Button type="button" size="lg" variant="outline" onClick={onChangeStatus}>
                        Change status
                    </Button>
                </div>
            )}
        </header>
    );
}
