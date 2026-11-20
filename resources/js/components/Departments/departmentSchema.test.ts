import { describe, expect, it } from 'vitest';
import {
    blankDepartmentValues,
    departmentSchema,
    toDepartmentInput,
    valuesFromDepartment,
} from '@/components/Departments/departmentSchema';
import { department } from '@/test/fixtures';

function problems(overrides: Record<string, string>): string[] {
    const result = departmentSchema.safeParse({ name: 'Choir', description: 'Sings.', head_user_id: '', ...overrides });

    return result.success ? [] : result.error.issues.map((issue) => String(issue.path[0]));
}

describe('departmentSchema', () => {
    it('needs a name that is not only spaces, up to 255 characters', () => {
        expect(problems({})).toEqual([]);
        expect(problems({ name: '  ' })).toEqual(['name']);
        expect(problems({ name: 'x'.repeat(255) })).toEqual([]);
        expect(problems({ name: 'x'.repeat(256) })).toEqual(['name']);
    });

    it('lets the description be empty, up to 2000 characters', () => {
        expect(problems({ description: '' })).toEqual([]);
        expect(problems({ description: 'x'.repeat(2001) })).toEqual(['description']);
    });

    it('lets the head be nobody or a staff member', () => {
        expect(problems({ head_user_id: '' })).toEqual([]);
        expect(problems({ head_user_id: 'user-1' })).toEqual([]);
    });
});

describe('department form values', () => {
    it('starts blank with no head, and fills from a saved department with no description or head as empty boxes', () => {
        expect(blankDepartmentValues()).toEqual({ name: '', description: '', head_user_id: '' });
        expect(valuesFromDepartment(department({ name: 'Ushers', description: null }))).toEqual({
            name: 'Ushers',
            description: '',
            head_user_id: '',
        });
        expect(valuesFromDepartment(department({ head_user_id: 'user-1', head: { id: 'user-1', name: 'Abena' } })).head_user_id).toBe(
            'user-1',
        );
    });

    it('sends an empty description as null, which clears it', () => {
        expect(toDepartmentInput({ name: 'Ushers', description: '', head_user_id: '' }, true)).toEqual({
            name: 'Ushers',
            description: null,
        });
        expect(toDepartmentInput({ name: 'Ushers', description: 'Welcome people.', head_user_id: '' }, true)).toEqual({
            name: 'Ushers',
            description: 'Welcome people.',
        });
    });
});

describe('the head in what is sent', () => {
    const headed = department({ head_user_id: 'user-1', head: { id: 'user-1', name: 'Abena' } });
    const values = { name: 'Choir', description: '', head_user_id: 'user-1' };

    it('sends a head chosen for a new department, and nothing when nobody is chosen', () => {
        expect(toDepartmentInput({ ...values, head_user_id: 'user-2' }, true)).toMatchObject({ head_user_id: 'user-2' });
        expect(toDepartmentInput({ ...values, head_user_id: '' }, true)).not.toHaveProperty('head_user_id');
    });

    it('sends nothing about the head when it is unchanged, so a head who has since been deactivated is not sent back', () => {
        expect(toDepartmentInput(values, true, headed)).not.toHaveProperty('head_user_id');
    });

    it('sends the new head when it is changed, and null when the head is taken away', () => {
        expect(toDepartmentInput({ ...values, head_user_id: 'user-2' }, true, headed)).toMatchObject({ head_user_id: 'user-2' });
        expect(toDepartmentInput({ ...values, head_user_id: '' }, true, headed)).toMatchObject({ head_user_id: null });
    });

    it('never touches the head when the list of staff has not loaded, even if the box looks emptied', () => {
        expect(toDepartmentInput({ ...values, head_user_id: '' }, false, headed)).not.toHaveProperty('head_user_id');
        expect(toDepartmentInput({ ...values, head_user_id: 'user-2' }, false, headed)).not.toHaveProperty('head_user_id');
    });
});
