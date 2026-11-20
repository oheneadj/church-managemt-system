import type { Permission } from '@/constants/permissions';
import { useAuthStore } from '@/stores/useAuthStore';

/**
 * Whether the signed-in user holds a permission. Screens use it to hide buttons the server would refuse
 * anyway; the server is what actually enforces it.
 */
export function useCan(permission: Permission): boolean {
    return useAuthStore((state) => state.user?.permissions.includes(permission) ?? false);
}

/**
 * Like `useCan`, but for a permission that may be absent: with none given, everyone is allowed. A menu uses it for items
 * that need no permission.
 */
export function useHasPermission(permission: Permission | undefined): boolean {
    return useAuthStore((state) => permission === undefined || (state.user?.permissions.includes(permission) ?? false));
}
