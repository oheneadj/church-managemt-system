import { z } from 'zod';
import { DEPARTMENT_DESCRIPTION_MAX, DEPARTMENT_NAME_MAX } from '@/constants/departments';
import type { Department, DepartmentInput } from '@/types/members';

/** The checks on the department form. The server also refuses a name another department of the church already has. */
export const departmentSchema = z.object({
    name: z
        .string()
        .trim()
        .min(1, 'Enter the name of the department')
        .max(DEPARTMENT_NAME_MAX, `Keep the name to ${DEPARTMENT_NAME_MAX} characters`),
    description: z.string().trim().max(DEPARTMENT_DESCRIPTION_MAX, `Keep the description to ${DEPARTMENT_DESCRIPTION_MAX} characters`),
});

export type DepartmentValues = z.infer<typeof departmentSchema>;

/** A new department starts with nothing filled in. */
export function blankDepartmentValues(): DepartmentValues {
    return { name: '', description: '' };
}

/** The form, filled in from a saved department. */
export function valuesFromDepartment(department: Department): DepartmentValues {
    return { name: department.name, description: department.description ?? '' };
}

/** Turns the form into what the API takes. An empty description clears it. */
export function toDepartmentInput(values: DepartmentValues): DepartmentInput {
    return { name: values.name, description: values.description === '' ? null : values.description };
}
