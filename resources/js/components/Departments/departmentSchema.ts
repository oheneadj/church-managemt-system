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
    /** The staff member chosen as head, or empty for nobody. */
    head_user_id: z.string(),
});

export type DepartmentValues = z.infer<typeof departmentSchema>;

/** A new department starts with nothing filled in and no head. */
export function blankDepartmentValues(): DepartmentValues {
    return { name: '', description: '', head_user_id: '' };
}

/** The form, filled in from a saved department. */
export function valuesFromDepartment(department: Department): DepartmentValues {
    return { name: department.name, description: department.description ?? '', head_user_id: department.head_user_id ?? '' };
}

/**
 * Turns the form into what the API takes. An empty description clears it. The head is sent only when it can be trusted to
 * be what the person meant: the list of staff has loaded (`headChoosable`), and the head has been changed from what was saved,
 * so a head who has since been deactivated is not sent back (the server would refuse them), and a list that failed to load
 * can never clear a head by accident. Choosing nobody is sent as null.
 */
export function toDepartmentInput(values: DepartmentValues, headChoosable: boolean, saved?: Department): DepartmentInput {
    const input: DepartmentInput = { name: values.name, description: values.description === '' ? null : values.description };
    const headChanged = values.head_user_id !== (saved?.head_user_id ?? '');

    if (headChoosable && headChanged) {
        input.head_user_id = values.head_user_id === '' ? null : values.head_user_id;
    }

    return input;
}
