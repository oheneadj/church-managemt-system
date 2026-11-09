import { useState } from 'react';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { useSimulatedAction } from '@/components/DesignSystem/useSimulatedAction';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

const SAVE_MS = 1500;

/** A dialog with a form that pretends to save: loading shows on its own button and it closes only when the work is done. */
function FormDialogDemo() {
    const [open, setOpen] = useState(false);
    const { state, run, reset } = useSimulatedAction(SAVE_MS, () => setOpen(false));

    const change = (next: boolean) => {
        setOpen(next);
        reset();
    };

    return (
        <Dialog open={open} onOpenChange={change}>
            <DialogTrigger asChild>
                <Button variant="outline">Open form dialog</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit name</DialogTitle>
                    <DialogDescription>Focus starts on the first field and returns to the button that opened this.</DialogDescription>
                </DialogHeader>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="demo-dialog-name">Name</Label>
                    <Input id="demo-dialog-name" defaultValue="Example" />
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => change(false)}>
                        Cancel
                    </Button>
                    <Button loading={state === 'working'} loadingLabel="Saving…" onClick={run}>
                        Save changes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Overlays and navigation: a form dialog, a confirmation for a destructive action, and tabs.
 */
export function OverlaysSection() {
    const [confirming, setConfirming] = useState(false);

    return (
        <ShowcaseSection
            id="overlays"
            title="Dialogs and tabs"
            description="Escape closes when it is safe. The confirmation names its action, says what happens, and focuses the safe choice first."
        >
            <div className="flex flex-wrap gap-2">
                <FormDialogDemo />
                <Button variant="destructive" onClick={() => setConfirming(true)}>
                    Delete item
                </Button>
            </div>
            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Delete this item?"
                description="This removes the item and cannot be undone."
                confirmLabel="Delete item"
                onConfirm={() => setConfirming(false)}
            />
            <Tabs defaultValue="one">
                <TabsList>
                    <TabsTrigger value="one">First</TabsTrigger>
                    <TabsTrigger value="two">Second</TabsTrigger>
                    <TabsTrigger value="three" disabled>
                        Disabled
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="one" className="text-sm text-muted-foreground">
                    Arrow keys move between tabs.
                </TabsContent>
                <TabsContent value="two" className="text-sm text-muted-foreground">
                    The content changes with the tab.
                </TabsContent>
            </Tabs>
        </ShowcaseSection>
    );
}
