import type { Permission } from '@/constants/permissions';
import { useAuthStore } from '@/stores/useAuthStore';

/**
 * Whether the signed-in user holds a permission. Screens use it to hide buttons the server would refuse
 * anyway; the server is what actually enforces it.
 */
export function useCan(permission: Permission): boolean {
    return useAuthStore((state) => state.user?.permissions.includes(permission) ?? false);
}
