import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface TopUpDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * What the Top up button does until buying credits exists (story 5.4, Sprint 5): it says so, instead of leading nowhere.
 * When the purchase is built, this dialog is replaced by the bundle choice and payment.
 */
export function TopUpDialog({ open, onOpenChange }: TopUpDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Top up SMS credits</DialogTitle>
                    <DialogDescription>
                        Buying credit bundles online is coming soon. Until then, ask the platform team to add credits for your church. Gift
                        receipts and other texts keep going out while there are credits left.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button onClick={() => onOpenChange(false)}>Got it</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
