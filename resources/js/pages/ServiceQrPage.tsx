import { QRCodeSVG } from 'qrcode.react';
import { Link, useParams } from 'react-router';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { Skeleton } from '@/components/ui/skeleton';
import { ROUTES } from '@/constants/routes';
import { useServiceQr } from '@/hooks/useServiceQr';
import { useService } from '@/hooks/useServices';
import { ApiError } from '@/services/ApiError';

/** The width of the QR code in pixels. It is large because it is read from across a room. */
const QR_SIZE = 360;

/**
 * The full-screen page shown on a screen at the venue (no menu, nothing else to read). It shows the QR code, which
 * changes every minute, and the address in words for anyone who cannot scan. Members scan it to check in.
 */
export function ServiceQrPage() {
    const { id = '' } = useParams();
    const service = useService(id);
    const qr = useServiceQr(id);
    const closed = qr.error instanceof ApiError && qr.error.status === 409;

    return (
        <main className="flex min-h-svh flex-col items-center justify-center gap-6 p-6 text-center">
            <h1 className="text-3xl font-semibold">{service.data?.name ?? 'Check in'}</h1>

            {qr.isPending && <Skeleton aria-busy="true" className="size-[360px] rounded-xl" />}

            {closed && (
                <div className="flex max-w-md flex-col gap-3">
                    <p className="text-xl">Check-in is closed.</p>
                    <p className="text-muted-foreground">Open check-in on the service page to show the QR code.</p>
                </div>
            )}

            {qr.isError && !closed && <ErrorNotice message={qr.error.message} onRetry={() => void qr.refetch()} />}

            {qr.data && (
                <>
                    <div className="rounded-2xl bg-white p-4">
                        <QRCodeSVG value={qr.data.url} size={QR_SIZE} title="QR code to check in to this service" />
                    </div>
                    <p className="text-xl">Scan with your phone camera to check in.</p>
                    <p className="max-w-lg break-all text-sm text-muted-foreground">{qr.data.url}</p>
                </>
            )}

            <Link to={ROUTES.SERVICE(id)} className="text-sm underline underline-offset-4">
                Back to the service
            </Link>
        </main>
    );
}
