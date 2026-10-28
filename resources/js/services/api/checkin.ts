import { api } from '@/services';
import type { OtpRequested } from '@/types/api';
import type { CheckinService, QrCheckedIn } from '@/types/services';

/** The public calls a member makes after scanning the QR code at the venue. No sign-in is involved. */
export const checkinApi = {
    /** Which service the scanned code is for. A rejection with status 404 means the code has expired. */
    service: (token: string): Promise<CheckinService> => api.get<CheckinService>(`/checkin/qr/${token}`).then((r) => r.data),

    /** Texts a check-in code to the phone. It answers the same for any number. */
    requestCode: (token: string, phone: string): Promise<OtpRequested> =>
        api.post<OtpRequested>('/checkin/qr/otp', { token, phone }).then((r) => r.data),

    /** Confirms the texted code and checks the member in. */
    confirm: (token: string, phone: string, otp: string): Promise<QrCheckedIn> =>
        api.post<QrCheckedIn>('/checkin/qr', { token, phone, otp }).then((r) => r.data),
};
