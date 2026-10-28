import { api } from '@/services';
import type { Department, Page } from '@/types/members';

/** The largest page the API allows; a church has few departments, so one call loads them all. */
const PICKER_PAGE_SIZE = 100;

export const departmentsApi = {
    list: (): Promise<Page<Department>> =>
        api.get<Page<Department>>('/departments', { params: { per_page: PICKER_PAGE_SIZE, is_active: 1 } }).then((r) => r.data),
};
