import { ExportButton } from '@/components/Exports/ExportButton';
import { useExportContributions } from '@/hooks/useExports';
import type { ContributionFilters } from '@/types/giving';

/** Exports the gifts with the dates, fund and way of giving now on screen. Voided gifts are always in the file, marked (D-064). */
export function ContributionExportButton({ filters }: { filters: ContributionFilters }) {
    const request = useExportContributions(filters);

    return (
        <ExportButton
            label="Export gifts"
            contents="the gifts the current filters find, oldest first, as a spreadsheet (CSV). Voided gifts are included and marked"
            request={request}
        />
    );
}
