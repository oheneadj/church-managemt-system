import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initialsOf } from '@/utils/initialsOf';

interface MemberAvatarProps {
    fullName: string;
    photoUrl: string | null;
    className?: string;
}

/**
 * A member's photo, or their initials when they have none. The name is always shown beside it,
 * so the image itself is decorative.
 */
export function MemberAvatar({ fullName, photoUrl, className }: MemberAvatarProps) {
    return (
        <Avatar className={className}>
            {photoUrl && <AvatarImage src={photoUrl} alt="" />}
            <AvatarFallback>{initialsOf(fullName)}</AvatarFallback>
        </Avatar>
    );
}
