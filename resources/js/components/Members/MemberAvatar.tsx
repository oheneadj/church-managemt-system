import { ProfileImage, type ProfileImageSize } from '@/components/ui/ProfileImage';

interface MemberAvatarProps {
    fullName: string;
    photoUrl: string | null;
    size?: ProfileImageSize;
}

/**
 * A member's photo, or their initials when they have none. The name is always shown beside it, so the image is decorative.
 */
export function MemberAvatar({ fullName, photoUrl, size = 'lg' }: MemberAvatarProps) {
    return <ProfileImage name={fullName} src={photoUrl} size={size} decorative />;
}
