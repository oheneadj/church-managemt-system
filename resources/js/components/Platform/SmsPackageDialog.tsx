import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { TextField } from '@/components/form/TextField';
import {
    blankPackageValues,
    smsPackageSchema,
    toPackageInput,
    valuesFromPackage,
    type SmsPackageValues,
} from '@/components/Platform/smsPackageSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PACKAGE_SORT_ORDER_MAX } from '@/constants/platform';
import { useSmsPackageMutations } from '@/hooks/usePlatform';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { SmsPackage } from '@/types/platform';

interface SmsPackageDialogProps {
    /** The package to change, or none to add a new one. */
    smsPackage?: SmsPackage;
    /** Every package now, so a new one can be placed after the last. */
    packages: readonly SmsPackage[];
    onClose: () => void;
}

const FIELDS = ['name', 'credits', 'price', 'sort_order'] as const;

/**
 * Adds an SMS credit package or changes one: its name, its credits, its price in GHS and its place in the list. Mount it only
 * while it is open. A name another package already has is refused by the server and shown beside the box. There is no
 * delete: a package no longer sold is switched off from the list.
 */
export function SmsPackageDialog({ smsPackage, packages, onClose }: SmsPackageDialogProps) {
    const { create, update } = useSmsPackageMutations();
    const form = useForm<SmsPackageValues>({
        resolver: zodResolver(smsPackageSchema),
        defaultValues: smsPackage === undefined ? blankPackageValues(packages) : valuesFromPackage(smsPackage),
    });
    const { errors } = form.formState;
    const pending = create.isPending || update.isPending;

    const save = form.handleSubmit((values) => {
        const input = toPackageInput(values);
        const done = {
            onSuccess: () => {
                toast.success(smsPackage === undefined ? `${input.name} is added.` : 'Changes saved.');
                onClose();
            },
            onError: (error: Error) => {
                if (showFieldErrors(error, form.setError, [...FIELDS]).length === 0) {
                    toast.error(error.message);
                }
            },
        };

        if (smsPackage === undefined) {
            create.mutate(input, done);
        } else {
            update.mutate({ id: smsPackage.id, input }, done);
        }
    });

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{smsPackage === undefined ? 'Add a package' : `Change ${smsPackage.name}`}</DialogTitle>
                    <DialogDescription>
                        A bundle of SMS credits a church can buy. One credit is one part of a text message.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={save} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="package-name"
                        label="Name"
                        required
                        autoComplete="off"
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <TextField
                        id="package-credits"
                        label="Credits"
                        type="number"
                        inputMode="numeric"
                        min={1}
                        required
                        error={errors.credits?.message}
                        {...form.register('credits')}
                    />
                    <TextField
                        id="package-price"
                        label="Price (GHS)"
                        inputMode="decimal"
                        placeholder="25.00"
                        required
                        autoComplete="off"
                        error={errors.price?.message}
                        {...form.register('price')}
                    />
                    <TextField
                        id="package-order"
                        label="Place in the list"
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={PACKAGE_SORT_ORDER_MAX}
                        error={errors.sort_order?.message}
                        {...form.register('sort_order')}
                    />
                    <p className="text-sm text-muted-foreground">Churches see packages from the lowest number to the highest.</p>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={pending}>
                            {pending ? 'Saving…' : smsPackage === undefined ? 'Add package' : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
