import { ExportButton } from '@/components/Exports/ExportButton';
import { useExportMembers } from '@/hooks/useExports';
import type { MemberFilters } from '@/types/members';

/** Exports the member list with the filters now on screen, so the file holds what the list shows (D-064). */
export function MemberExportButton({ filters }: { filters: MemberFilters }) {
    const request = useExportMembers(filters);

    return <ExportButton label="Export list" contents="everyone the current filters find, as a spreadsheet (CSV)" request={request} />;
}
