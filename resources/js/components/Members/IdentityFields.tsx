import { useFormContext } from 'react-hook-form';
import { SelectField } from '@/components/form/SelectField';
import { TextField } from '@/components/form/TextField';
import type { MemberFormValues } from '@/components/Members/memberFormSchema';
import { GENDERS, MARITAL_STATUSES } from '@/constants/enums';
import { today } from '@/utils/today';

/**
 * Step 1 of the member form: name, gender, date of birth and marital status.
 */
export function IdentityFields() {
    const { register, formState } = useFormContext<MemberFormValues>();
    const { errors } = formState;

    return (
        <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
            <TextField
                id="first_name"
                label="First name"
                required
                autoComplete="given-name"
                placeholder="Kwame"
                error={errors.first_name?.message}
                {...register('first_name')}
            />
            <TextField
                id="last_name"
                label="Last name"
                required
                autoComplete="family-name"
                placeholder="Mensah"
                error={errors.last_name?.message}
                {...register('last_name')}
            />
            <TextField
                id="middle_name"
                label="Middle name"
                placeholder="Kofi"
                error={errors.middle_name?.message}
                {...register('middle_name')}
            />
            <TextField
                id="preferred_name"
                label="Known as"
                placeholder="Kofi"
                error={errors.preferred_name?.message}
                {...register('preferred_name')}
            />
            <SelectField
                id="gender"
                label="Gender"
                required
                options={GENDERS}
                emptyLabel="Choose"
                error={errors.gender?.message}
                {...register('gender')}
            />
            <TextField
                id="date_of_birth"
                label="Date of birth"
                type="date"
                max={today()}
                autoComplete="bday"
                error={errors.date_of_birth?.message}
                {...register('date_of_birth')}
            />
            <SelectField
                id="marital_status"
                label="Marital status"
                options={MARITAL_STATUSES}
                emptyLabel="Not stated"
                error={errors.marital_status?.message}
                {...register('marital_status')}
            />
        </div>
    );
}
