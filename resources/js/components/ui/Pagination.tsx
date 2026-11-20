import { Button } from '@/components/ui/button';

interface PaginationProps {
    page: number;
    lastPage: number;
    onPageChange: (page: number) => void;
    /** True while the next page is loading, so the buttons wait instead of stacking requests. */
    disabled?: boolean;
}

/**
 * Previous and next buttons with "Page X of Y". Hidden when everything fits on one page.
 */
export function Pagination({ page, lastPage, onPageChange, disabled = false }: PaginationProps) {
    if (lastPage <= 1) {
        return null;
    }

    return (
        <nav aria-label="Pages" className="flex items-center justify-between gap-2">
            <Button variant="outline" disabled={disabled || page <= 1} onClick={() => onPageChange(page - 1)}>
                Previous
            </Button>
            <span className="text-sm text-muted-foreground">
                Page {page} of {lastPage}
            </span>
            <Button variant="outline" disabled={disabled || page >= lastPage} onClick={() => onPageChange(page + 1)}>
                Next
            </Button>
        </nav>
    );
}
