/**
 * Full-area loading indicator shown while a lazy page loads.
 */
export function PageSpinner() {
    return (
        <div role="status" aria-live="polite" className="flex min-h-40 items-center justify-center text-sm text-muted-foreground">
            Loading…
        </div>
    );
}
