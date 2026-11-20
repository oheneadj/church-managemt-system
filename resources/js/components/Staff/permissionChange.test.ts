import { describe, expect, it } from 'vitest';
import { permissionChange } from '@/components/Staff/permissionChange';

const pastor = { role_permissions: ['members.view', 'sms.send'], extra_permissions: ['giving.export'] };

describe('permissionChange', () => {
    it('sends nothing when the ticks are what the person already has', () => {
        expect(permissionChange(pastor, ['giving.export'])).toBeNull();
        expect(permissionChange(pastor, ['giving.export', 'members.view', 'sms.send'])).toBeNull();
    });

    it('grants a permission that is newly ticked', () => {
        expect(permissionChange(pastor, ['giving.export', 'members.export'])).toEqual({ grant: ['members.export'] });
    });

    it('takes away an extra that is unticked', () => {
        expect(permissionChange(pastor, [])).toEqual({ revoke: ['giving.export'] });
    });

    it('grants and takes away in one change', () => {
        expect(permissionChange(pastor, ['members.export'])).toEqual({ grant: ['members.export'], revoke: ['giving.export'] });
    });

    it('never grants or takes away a permission the role gives, whatever is ticked', () => {
        expect(permissionChange(pastor, ['giving.export', 'members.view'])).toBeNull();
        expect(permissionChange(pastor, [])).toEqual({ revoke: ['giving.export'] });
    });
});
