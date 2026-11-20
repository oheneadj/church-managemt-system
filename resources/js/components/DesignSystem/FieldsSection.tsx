import { useState } from 'react';
import { ShowcaseSection } from '@/components/DesignSystem/ShowcaseSection';
import { FormField } from '@/components/form/FormField';
import { PasswordField } from '@/components/form/PasswordField';
import { PasswordRequirements } from '@/components/form/PasswordRequirements';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

/**
 * Fields: the states of a text input, a text area, and the password field with its live requirements.
 */
export function FieldsSection() {
    const [password, setPassword] = useState('');

    return (
        <ShowcaseSection
            id="fields"
            title="Fields"
            description="Every field has a label that stays. The placeholder is only an example. Errors say how to fix it and are tied to the field."
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField id="demo-empty" label="Email address">
                    <Input id="demo-empty" type="email" autoComplete="off" placeholder="name@example.com" />
                </FormField>
                <FormField id="demo-filled" label="Filled">
                    <Input id="demo-filled" defaultValue="Something typed" />
                </FormField>
                <FormField id="demo-invalid" label="With an error" error="Enter an email address like name@example.com.">
                    <Input id="demo-invalid" defaultValue="name@" aria-invalid="true" aria-describedby="demo-invalid-error" />
                </FormField>
                <FormField id="demo-disabled" label="Disabled">
                    <Input id="demo-disabled" defaultValue="Cannot be changed" disabled />
                </FormField>
                <FormField id="demo-readonly" label="Read-only">
                    <Input id="demo-readonly" defaultValue="Can be selected, not edited" readOnly />
                </FormField>
                <FormField id="demo-area" label="Notes (optional)">
                    <Textarea id="demo-area" placeholder="Anything worth remembering" />
                </FormField>
            </div>
            <div className="flex max-w-sm flex-col gap-4">
                <PasswordField
                    id="demo-password"
                    label="Choose a password"
                    autoComplete="new-password"
                    value={password}
                    onChange={(event) => setPassword(event.target.value)}
                />
                <PasswordRequirements value={password} />
            </div>
        </ShowcaseSection>
    );
}
