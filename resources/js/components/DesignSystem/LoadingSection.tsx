import { Loader2Icon } from 'lucide-react';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { Skeleton } from '@/components/ui/skeleton';

const SPINNER_SIZES = [
    ['Small', 'var(--size-spinner-sm)'],
    ['Medium', 'var(--size-spinner-md)'],
    ['Large', 'var(--size-spinner-lg)'],
] as const;

/**
 * Loading: a skeleton shaped like the content it stands in for, and the three spinner sizes.
 */
export function LoadingSection() {
    return (
        <ShowcaseSection
            id="loading"
            title="Loading"
            description="A skeleton keeps the shape of the content so nothing jumps when it arrives. It is announced once, not shape by shape."
        >
            <div role="status" aria-label="Loading content" className="flex max-w-md items-center gap-3 rounded-xl border p-4">
                <Skeleton className="size-12 rounded-full" />
                <div className="flex flex-1 flex-col gap-2">
                    <Skeleton className="h-4 w-2/3" />
                    <Skeleton className="h-3 w-full" />
                </div>
            </div>
            <div className="flex items-center gap-6 text-sm text-muted-foreground">
                {SPINNER_SIZES.map(([name, size]) => (
                    <span key={name} className="flex items-center gap-2">
                        <Loader2Icon aria-hidden="true" className="animate-spin" style={{ width: size, height: size }} />
                        {name}
                    </span>
                ))}
            </div>
        </ShowcaseSection>
    );
}
