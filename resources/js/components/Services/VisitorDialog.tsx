import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { z } from 'zod';
import { TextField } from '@/components/form/TextField';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { PHONE_INPUT_PATTERN } from '@/constants/validation';
import { useAttendanceMutations } from '@/hooks/useServiceAttendance';
import { showFieldErrors } from '@/lib/showFieldErrors';

const schema = z.object({
    name: z.string().trim().min(1, "Enter the visitor's name").max(255, 'Keep it under 255 characters'),
    phone: z
        .string()
        .trim()
        .refine((phone) => phone === '' || PHONE_INPUT_PATTERN.test(phone), { error: 'Enter a valid phone number' }),
    how_heard: z.string().trim().max(255, 'Keep it under 255 characters'),
});

type FormValues = z.infer<typeof schema>;

interface VisitorDialogProps {
    serviceId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Welcomes a first-time visitor: a name is enough, a phone number lets the church follow up and recognise them when they return.
 */
export function VisitorDialog({ serviceId, open, onOpenChange }: VisitorDialogProps) {
    const { captureVisitor } = useAttendanceMutations(serviceId);
    const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { name: '', phone: '', how_heard: '' } });
    const { errors } = form.formState;

    const submit = form.handleSubmit((values) => {
        captureVisitor.mutate(
            { name: values.name, phone: values.phone || undefined, how_heard: values.how_heard || undefined },
            {
                onSuccess: (visitor) => {
                    toast.success(visitor.visit_count > 1 ? `Welcome back, ${visitor.name}.` : `${visitor.name} was added as a visitor.`);
                    form.reset();
                    onOpenChange(false);
                },
                onError: (error) => {
                    if (showFieldErrors(error, form.setError, ['name', 'phone', 'how_heard']).length === 0) {
                        toast.error(error.message);
                    }
                },
            },
        );
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add a visitor</DialogTitle>
                    <DialogDescription>They are counted at this service and kept apart from members.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="visitor-name"
                        label="Name"
                        required
                        placeholder="Yaw Boateng"
                        autoComplete="off"
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <TextField
                        id="visitor-phone"
                        label="Phone number (optional)"
                        type="tel"
                        inputMode="tel"
                        placeholder="024 123 4567"
                        autoComplete="off"
                        error={errors.phone?.message}
                        {...form.register('phone')}
                    />
                    <TextField
                        id="visitor-heard"
                        label="How did they hear about us? (optional)"
                        placeholder="A friend"
                        autoComplete="off"
                        error={errors.how_heard?.message}
                        {...form.register('how_heard')}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={captureVisitor.isPending}>
                            {captureVisitor.isPending ? 'Saving…' : 'Add visitor'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
