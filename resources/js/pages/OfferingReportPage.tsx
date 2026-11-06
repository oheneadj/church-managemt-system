import { HandCoins } from 'lucide-react';
import { Link, useParams } from 'react-router';
import { OfferingTable } from '@/components/Giving/OfferingTable';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/EmptyState';
import { ErrorNotice } from '@/components/ui/ErrorNotice';
import { MoneyDisplay } from '@/components/ui/MoneyDisplay';
import { PageHeader } from '@/components/ui/PageHeader';
import { Skeleton } from '@/components/ui/skeleton';
import { ROUTES } from '@/constants/routes';
import { useOfferingReport } from '@/hooks/useOfferingReport';
import { ApiError } from '@/services/ApiError';
import { useChurchStore } from '@/stores/useChurchStore';
import { formatDate } from '@/utils/formatDate';

/**
 * What one service received, by fund and by how it was given, ready to print or save as a PDF from the browser's print
 * window. The app menu is hidden when printing. It shows totals only, with no giver's name, so the pastor may read it.
 */
export function OfferingReportPage() {
    const { id = '' } = useParams();
    const report = useOfferingReport(id);
    const churchName = useChurchStore((state) => state.church?.name);

    if (report.isPending) {
        return <Skeleton aria-busy="true" className="mx-auto h-64 max-w-3xl rounded-xl" />;
    }

    if (report.isError) {
        const refused = report.error instanceof ApiError && (report.error.status === 404 || report.error.status === 403);

        return (
            <div className="mx-auto flex max-w-3xl flex-col gap-3">
                <ErrorNotice
                    message={refused ? 'This report was not found.' : report.error.message}
                    onRetry={refused ? undefined : () => void report.refetch()}
                />
                <Link to={ROUTES.SERVICES} className="text-sm underline underline-offset-4">
                    Back to services
                </Link>
            </div>
        );
    }

    const { service, totals, no_service: noService } = report.data;
    const showNoService = noService.counted_here && noService.transaction_count > 0;
    const nothingGiven = totals.transaction_count === 0 && !showNoService;

    return (
        <div className="mx-auto flex max-w-3xl flex-col gap-6">
            <Link to={ROUTES.SERVICE(service.id)} className="text-sm underline underline-offset-4 print:hidden">
                ← Back to the service
            </Link>
            <PageHeader
                title="Offering report"
                description={`${churchName ?? ''} · ${service.name} · ${formatDate(service.service_date)}`}
                actions={
                    <Button variant="outline" size="lg" className="print:hidden" onClick={() => window.print()}>
                        Print or save as PDF
                    </Button>
                }
            />

            {nothingGiven && (
                <EmptyState icon={HandCoins} title="Nothing was given" description="Gifts recorded for this service will appear here." />
            )}

            {!nothingGiven && (
                <>
                    {totals.transaction_count > 0 && <OfferingTable title="Given at this service" section={totals} />}
                    {showNoService && <OfferingTable title="Given that day with no service" section={noService} />}
                    <p className="flex items-center justify-between border-t pt-3 font-semibold">
                        <span>Total for the day</span>
                        <MoneyDisplay formatted={report.data.day_grand_total_formatted} />
                    </p>
                    <p className="text-sm text-muted-foreground">Voided gifts are not counted.</p>
                </>
            )}
        </div>
    );
}
