import { api } from '@/services';
import type { Department, DepartmentInput, Page } from '@/types/members';

/** The largest page the API allows; a church has few departments, so one call loads them all. */
const PICKER_PAGE_SIZE = 100;

export const departmentsApi = {
    list: (): Promise<Page<Department>> =>
        api.get<Page<Department>>('/departments', { params: { per_page: PICKER_PAGE_SIZE, is_active: 1 } }).then((r) => r.data),

    /** Every department, retired ones included, by name, for the screen that manages them. */
    all: (): Promise<Department[]> =>
        api.get<Page<Department>>('/departments', { params: { per_page: PICKER_PAGE_SIZE } }).then((r) => r.data.data),

    create: (input: DepartmentInput): Promise<Department> => api.post<{ data: Department }>('/departments', input).then((r) => r.data.data),

    /** Changes the fields sent. The head is not sent, so it is left as it is. */
    update: (id: string, input: Partial<DepartmentInput>): Promise<Department> =>
        api.put<{ data: Department }>(`/departments/${id}`, input).then((r) => r.data.data),

    /** Retires a department or brings it back. Members already in it stay in it. */
    setActive: (id: string, isActive: boolean): Promise<Department> =>
        api.put<{ data: Department }>(`/departments/${id}`, { is_active: isActive }).then((r) => r.data.data),
};
