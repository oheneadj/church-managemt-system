import { useFormContext } from 'react-hook-form';
import { CheckboxField } from '@/components/form/CheckboxField';
import { SelectField } from '@/components/form/SelectField';
import { TextField } from '@/components/form/TextField';
import type { MemberFormValues } from '@/components/Members/memberFormSchema';
import { EDUCATIONAL_LEVELS, FAMILY_ROLES, MEMBERSHIP_TYPES, NEW_MEMBER_STATUSES } from '@/constants/enums';
import { useDepartments } from '@/hooks/useDepartments';
import { useAllFamilies } from '@/hooks/useFamilies';

interface ChurchFieldsProps {
    /** A new member chooses a starting status; an existing one changes status from the profile instead. */
    editing: boolean;
}

/**
 * Step 3 of the member form: membership, education, family, departments and how the member may be messaged.
 */
export function ChurchFields({ editing }: ChurchFieldsProps) {
    const { register, watch, formState } = useFormContext<MemberFormValues>();
    const { errors } = formState;
    const families = useAllFamilies();
    const departments = useDepartments();
    const inAFamily = watch('family_id') !== '';

    const familyOptions = (families.data ?? []).map((family) => ({ value: family.id, label: family.name }));

    return (
        <div className="flex flex-col gap-2">
            <div className="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                <SelectField
                    id="membership_type"
                    label="Membership type"
                    required
                    options={MEMBERSHIP_TYPES}
                    error={errors.membership_type?.message}
                    {...register('membership_type')}
                />
                <TextField
                    id="membership_date"
                    label="Member since"
                    type="date"
                    error={errors.membership_date?.message}
                    {...register('membership_date')}
                />
                {!editing && (
                    <SelectField
                        id="status"
                        label="Starting status"
                        options={NEW_MEMBER_STATUSES}
                        error={errors.status?.message}
                        {...register('status')}
                    />
                )}
                <SelectField
                    id="educational_level"
                    label="Education"
                    options={EDUCATIONAL_LEVELS}
                    emptyLabel="Not stated"
                    error={errors.educational_level?.message}
                    {...register('educational_level')}
                />
                <TextField id="occupation" label="Occupation" error={errors.occupation?.message} {...register('occupation')} />
                <TextField id="tribe" label="Tribe" error={errors.tribe?.message} {...register('tribe')} />
                <TextField
                    id="language_preference"
                    label="Preferred language"
                    placeholder="Twi, Ga, English"
                    error={errors.language_preference?.message}
                    {...register('language_preference')}
                />
                <TextField
                    id="national_id_type"
                    label="ID type"
                    placeholder="Ghana Card"
                    error={errors.national_id_type?.message}
                    {...register('national_id_type')}
                />
                <TextField
                    id="national_id_number"
                    label={editing ? 'ID number (leave empty to keep the saved one)' : 'ID number'}
                    autoComplete="off"
                    error={errors.national_id_number?.message}
                    {...register('national_id_number')}
                />
                <SelectField
                    id="family_id"
                    label="Family"
                    options={familyOptions}
                    emptyLabel="No family"
                    error={errors.family_id?.message}
                    {...register('family_id')}
                />
                {inAFamily && (
                    <SelectField
                        id="family_role"
                        label="Role in the family"
                        required
                        options={FAMILY_ROLES}
                        emptyLabel="Choose"
                        error={errors.family_role?.message}
                        {...register('family_role')}
                    />
                )}
            </div>

            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium">Departments</legend>
                {departments.data?.data.length === 0 && (
                    <p className="text-sm text-muted-foreground">No departments have been set up yet.</p>
                )}
                {departments.data?.data.map((department) => (
                    <CheckboxField
                        key={department.id}
                        id={`department-${department.id}`}
                        label={department.name}
                        value={department.id}
                        {...register('department_ids')}
                    />
                ))}
            </fieldset>

            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium">May we message them?</legend>
                <CheckboxField id="sms_opted_in" label="Text messages" {...register('sms_opted_in')} />
                <CheckboxField id="whatsapp_opted_in" label="WhatsApp messages" {...register('whatsapp_opted_in')} />
            </fieldset>
        </div>
    );
}
