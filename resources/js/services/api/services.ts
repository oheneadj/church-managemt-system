import { api } from '@/services';
import type { Page } from '@/types/members';
import type { Service, ServiceFilters, ServiceInput, ServiceQrCode } from '@/types/services';

export const servicesApi = {
    list: (filters: ServiceFilters): Promise<Page<Service>> =>
        api
            .get<Page<Service>>('/services', { params: { page: filters.page, ...(filters.type === '' ? {} : { type: filters.type }) } })
            .then((r) => r.data),

    get: (id: string): Promise<Service> => api.get<{ data: Service }>(`/services/${id}`).then((r) => r.data.data),

    create: (input: ServiceInput): Promise<Service> => api.post<{ data: Service }>('/services', input).then((r) => r.data.data),

    update: (id: string, input: Partial<ServiceInput>): Promise<Service> =>
        api.put<{ data: Service }>(`/services/${id}`, input).then((r) => r.data.data),

    /** A fresh code to show as a QR code. */
    qr: (id: string): Promise<ServiceQrCode> => api.get<ServiceQrCode>(`/services/${id}/qr`).then((r) => r.data),

    /** Opens or closes the service for check-in. */
    setCheckin: (id: string, isOpen: boolean): Promise<Service> =>
        api.patch<{ data: Service }>(`/services/${id}/checkin`, { is_open: isOpen }).then((r) => r.data.data),
};
