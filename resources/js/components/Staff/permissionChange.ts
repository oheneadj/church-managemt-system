import type { StaffMember, StaffPermissionChange } from '@/types/staff';

/**
 * What to send to move a person from the extra permissions they have to the ones now ticked. Only extras can be given or taken
 * away: a permission the role gives is neither added nor removed, so it is left out of both lists. Returns null when nothing
 * changed, so the screen sends nothing (the server wants at least one list).
 */
export function permissionChange(
    staff: Pick<StaffMember, 'role_permissions' | 'extra_permissions'>,
    ticked: readonly string[],
): StaffPermissionChange | null {
    const grant = ticked.filter(
        (permission) => !staff.role_permissions.includes(permission) && !staff.extra_permissions.includes(permission),
    );
    const revoke = staff.extra_permissions.filter((permission) => !ticked.includes(permission));

    if (grant.length === 0 && revoke.length === 0) {
        return null;
    }

    return { ...(grant.length > 0 ? { grant } : {}), ...(revoke.length > 0 ? { revoke } : {}) };
}
