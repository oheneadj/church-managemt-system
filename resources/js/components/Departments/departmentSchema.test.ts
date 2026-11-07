import { describe, expect, it } from 'vitest';
import {
    blankDepartmentValues,
    departmentSchema,
    toDepartmentInput,
    valuesFromDepartment,
} from '@/components/Departments/departmentSchema';
import { department } from '@/test/fixtures';

function problems(overrides: Record<string, string>): string[] {
    const result = departmentSchema.safeParse({ name: 'Choir', description: 'Sings.', ...overrides });

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
});

describe('department form values', () => {
    it('starts blank, and fills from a saved department with no description as an empty box', () => {
        expect(blankDepartmentValues()).toEqual({ name: '', description: '' });
        expect(valuesFromDepartment(department({ name: 'Ushers', description: null }))).toEqual({ name: 'Ushers', description: '' });
    });

    it('sends an empty description as null, which clears it', () => {
        expect(toDepartmentInput({ name: 'Ushers', description: '' })).toEqual({ name: 'Ushers', description: null });
        expect(toDepartmentInput({ name: 'Ushers', description: 'Welcome people.' })).toEqual({
            name: 'Ushers',
            description: 'Welcome people.',
        });
    });
});
