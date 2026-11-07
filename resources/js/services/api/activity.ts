import { api } from '@/services';
import type { ActivityLine } from '@/types/activity';
import type { Page } from '@/types/members';

export const activityApi = {
    /** The latest lines of activity, newest first, as the server wrote them. Each person gets only what they may see (D-068). */
    recent: (count: number): Promise<Page<ActivityLine>> =>
        api.get<Page<ActivityLine>>('/activity', { params: { per_page: count } }).then((r) => r.data),
};
