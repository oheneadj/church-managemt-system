import { SaveIcon } from 'lucide-react';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { useSimulatedAction } from '@/components/DesignSystem/useSimulatedAction';
import { Button } from '@/components/ui/button';

/** How long the quick and slow demonstrations take. The quick one ends before the loading indicator would appear. */
const QUICK_MS = 250;
const SLOW_MS = 2500;

interface SaveDemoProps {
    label: string;
    durationMs: number;
}

/** A save button that pretends to work, to show what the person sees and that a second press does nothing. */
function SaveDemo({ label, durationMs }: SaveDemoProps) {
    const { state, run, reset } = useSimulatedAction(durationMs);

    return (
        <div className="flex flex-col items-start gap-2">
            <Button loading={state === 'working'} loadingLabel="Saving…" onClick={run}>
                <SaveIcon aria-hidden="true" /> {label}
            </Button>
            <p role="status" className="min-h-5 text-sm text-muted-foreground">
                {state === 'done' ? (
                    <>
                        Changes saved.{' '}
                        <button type="button" className="underline underline-offset-4" onClick={reset}>
                            Start again
                        </button>
                    </>
                ) : null}
            </p>
        </div>
    );
}

/**
 * Buttons: every variant and size, the disabled look, and a loading button that shows no spinner for quick work.
 */
export function ButtonsSection() {
    return (
        <ShowcaseSection
            id="buttons"
            title="Buttons"
            description="One primary action per view. Press, tab to and hold each one. The two save buttons show loading: the quick one never flashes a spinner."
        >
            <div className="flex flex-wrap items-center gap-2">
                <Button>Primary</Button>
                <Button variant="secondary">Secondary</Button>
                <Button variant="outline">Outline</Button>
                <Button variant="ghost">Ghost</Button>
                <Button variant="destructive">Delete item</Button>
                <Button variant="link">Link</Button>
                <Button disabled>Disabled</Button>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Button size="xs">Extra small</Button>
                <Button size="sm">Small</Button>
                <Button>Default</Button>
                <Button size="lg">Large</Button>
            </div>
            <div className="flex flex-wrap gap-8">
                <SaveDemo label="Save changes (quick)" durationMs={QUICK_MS} />
                <SaveDemo label="Save changes (slow)" durationMs={SLOW_MS} />
            </div>
        </ShowcaseSection>
    );
}
