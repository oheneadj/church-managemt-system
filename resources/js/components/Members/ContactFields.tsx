import { useFormContext } from 'react-hook-form';
import { TextAreaField } from '@/components/form/TextAreaField';
import { TextField } from '@/components/form/TextField';
import type { MemberFormValues } from '@/components/Members/memberFormSchema';

/**
 * Step 2 of the member form: phone numbers, email and where the member lives. The nearest landmark matters
 * because many addresses are described by it ("near the Total filling station").
 */
export function ContactFields() {
    const { register, formState } = useFormContext<MemberFormValues>();
    const { errors } = formState;

    return (
        <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
            <TextField
                id="phone"
                label="Phone number"
                type="tel"
                inputMode="tel"
                required
                autoComplete="tel"
                placeholder="024 123 4567"
                error={errors.phone?.message}
                {...register('phone')}
            />
            <TextField
                id="phone_secondary"
                label="Second phone number"
                type="tel"
                inputMode="tel"
                error={errors.phone_secondary?.message}
                {...register('phone_secondary')}
            />
            <TextField id="email" label="Email" type="email" autoComplete="email" error={errors.email?.message} {...register('email')} />
            <TextField
                id="digital_address"
                label="Digital address (GPS code)"
                placeholder="GA-042-1234"
                error={errors.digital_address?.message}
                {...register('digital_address')}
            />
            <div className="sm:col-span-2">
                <TextAreaField
                    id="address"
                    label="Home address"
                    autoComplete="street-address"
                    error={errors.address?.message}
                    {...register('address')}
                />
            </div>
            <div className="sm:col-span-2">
                <TextField
                    id="nearest_landmark"
                    label="Nearest landmark"
                    placeholder="Near the Total filling station"
                    error={errors.nearest_landmark?.message}
                    {...register('nearest_landmark')}
                />
            </div>
        </div>
    );
}
