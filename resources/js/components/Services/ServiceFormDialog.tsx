import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { SelectField } from '@/components/form/SelectField';
import { TextAreaField } from '@/components/form/TextAreaField';
import { TextField } from '@/components/form/TextField';
import { serviceFormSchema, toServiceInput, type ServiceFormValues } from '@/components/Services/serviceFormSchema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { SERVICE_TYPES } from '@/constants/enums';
import { useServiceMutations } from '@/hooks/useServices';
import { showFieldErrors } from '@/lib/showFieldErrors';
import type { Service } from '@/types/services';

interface ServiceFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Called with the new service, for example to open its page. */
    onSaved?: (service: Service) => void;
}

const BLANK: Partial<ServiceFormValues> = { name: '', type: 'sunday', service_date: '', starts_at: '', ends_at: '', notes: '' };

/**
 * A small form to create a service. The check-in code is made by the system, and the service starts closed.
 */
export function ServiceFormDialog({ open, onOpenChange, onSaved }: ServiceFormDialogProps) {
    const { create } = useServiceMutations();
    const form = useForm<ServiceFormValues>({ resolver: zodResolver(serviceFormSchema), defaultValues: BLANK });
    const { errors } = form.formState;

    const submit = form.handleSubmit((values) => {
        create.mutate(toServiceInput(values), {
            onSuccess: (service) => {
                toast.success('Service created.');
                form.reset(BLANK);
                onOpenChange(false);
                onSaved?.(service);
            },
            onError: (error) => {
                if (showFieldErrors(error, form.setError, ['name', 'type', 'service_date', 'starts_at', 'ends_at', 'notes']).length === 0) {
                    toast.error(error.message);
                }
            },
        });
    });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>New service</DialogTitle>
                    <DialogDescription>You open it for check-in on the day. A check-in code is made for you.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} noValidate className="flex flex-col gap-2">
                    <TextField
                        id="service-name"
                        label="Name"
                        required
                        placeholder="Sunday Morning Service"
                        error={errors.name?.message}
                        {...form.register('name')}
                    />
                    <SelectField
                        id="service-type"
                        label="Type"
                        required
                        options={SERVICE_TYPES}
                        error={errors.type?.message}
                        {...form.register('type')}
                    />
                    <TextField
                        id="service-date"
                        label="Date"
                        type="date"
                        required
                        error={errors.service_date?.message}
                        {...form.register('service_date')}
                    />
                    <div className="grid grid-cols-2 gap-3">
                        <TextField
                            id="service-starts"
                            label="Starts (optional)"
                            type="time"
                            error={errors.starts_at?.message}
                            {...form.register('starts_at')}
                        />
                        <TextField
                            id="service-ends"
                            label="Ends (optional)"
                            type="time"
                            error={errors.ends_at?.message}
                            {...form.register('ends_at')}
                        />
                    </div>
                    <TextAreaField
                        id="service-notes"
                        label="Notes (optional)"
                        placeholder="Anything the ushers should know"
                        error={errors.notes?.message}
                        {...form.register('notes')}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={create.isPending}>
                            {create.isPending ? 'Saving…' : 'Create service'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
