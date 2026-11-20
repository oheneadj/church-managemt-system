import { api } from '@/services';
import type { ChurchProfile, ChurchProfileInput, ChurchSettings } from '@/types/church';

export const churchApi = {
    /** The church's own details. Any signed-in staff member may read them. */
    profile: (): Promise<ChurchProfile> => api.get<{ data: ChurchProfile }>('/church').then((r) => r.data.data),

    /** Changes the details. Only the church admin may; the server records only what really changed. */
    updateProfile: (input: ChurchProfileInput): Promise<ChurchProfile> =>
        api.put<{ data: ChurchProfile }>('/church', input).then((r) => r.data.data),

    /** Every setting, with the church's choice or the default. */
    settings: (): Promise<ChurchSettings> => api.get<{ data: ChurchSettings }>('/church/settings').then((r) => r.data.data),

    /** Changes the settings sent and answers with all of them. */
    updateSettings: (settings: ChurchSettings): Promise<ChurchSettings> =>
        api.put<{ data: ChurchSettings }>('/church/settings', { settings }).then((r) => r.data.data),
};
