import { CircleCheckIcon, CircleIcon } from 'lucide-react';
import { PASSWORD_MIN_LENGTH } from '@/constants/password';

interface PasswordRequirementsProps {
    /** What has been typed so far. */
    value: string;
}

/**
 * What a new password has to be, ticked off as the person types (docs/design-system/interactions.md, section 8). Each line says
 * in words whether it is met, as well as showing an icon, so it does not depend on colour. It only guides: the server and the
 * form's rules are what enforce it, and it adds no rule of its own beyond the length (D-054).
 */
export function PasswordRequirements({ value }: PasswordRequirementsProps) {
    const longEnough = value.length >= PASSWORD_MIN_LENGTH;
    const Icon = longEnough ? CircleCheckIcon : CircleIcon;

    return (
        <ul aria-label="Password requirements" className="-mt-2 flex flex-col gap-1 text-sm text-muted-foreground">
            <li className="flex items-center gap-1.5">
                <Icon aria-hidden="true" className="size-4 shrink-0" />
                <span>
                    At least {PASSWORD_MIN_LENGTH} characters
                    <span className="sr-only">{longEnough ? ': met' : ': not met yet'}</span>
                </span>
            </li>
            <li>A few ordinary words together make a strong one.</li>
        </ul>
    );
}
