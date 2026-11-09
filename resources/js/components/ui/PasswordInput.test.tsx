import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { createRef } from 'react';
import { describe, expect, it, onTestFinished, vi } from 'vitest';
import { PasswordField } from '@/components/form/PasswordField';
import { PasswordRequirements } from '@/components/form/PasswordRequirements';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { PASSWORD_MIN_LENGTH } from '@/constants/password';

// The password field contract (docs/design-system/interactions.md, section 8): hidden by default, shown only when asked, nothing
// lost or moved when it is shown or hidden, and a named button that works by keyboard and by touch.

function field() {
    render(<PasswordInput id="password" aria-label="Password" />);

    return screen.getByLabelText('Password');
}

describe('PasswordInput', () => {
    it('hides what is typed until the person asks, and offers to show it', () => {
        const input = field();

        expect(input).toHaveAttribute('type', 'password');
        expect(screen.getByRole('button', { name: 'Show password' })).toHaveAttribute('aria-pressed', 'false');
    });

    it('shows the password when the eye is pressed and offers to hide it again', async () => {
        const user = userEvent.setup();
        const input = field();

        await user.click(screen.getByRole('button', { name: 'Show password' }));

        expect(input).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Hide password' })).toHaveAttribute('aria-pressed', 'true');

        await user.click(screen.getByRole('button', { name: 'Hide password' }));

        expect(input).toHaveAttribute('type', 'password');
        expect(screen.getByRole('button', { name: 'Show password' })).toBeInTheDocument();
    });

    it('never changes or clears what was typed, whichever way it is toggled', async () => {
        const user = userEvent.setup();
        const input = field();
        await user.type(input, 'correct horse battery');

        await user.click(screen.getByRole('button', { name: 'Show password' }));
        expect(input).toHaveValue('correct horse battery');
        await user.click(screen.getByRole('button', { name: 'Hide password' }));
        expect(input).toHaveValue('correct horse battery');
    });

    it('keeps the focus in the field and the caret where it was when pressed with a mouse or a finger, even in a browser that moves the caret to the end when the type changes', async () => {
        // Some browsers send the caret to the end when a field changes type. jsdom does not, so this does it.
        const original = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'type');
        const restore = () => Object.defineProperty(HTMLInputElement.prototype, 'type', original as PropertyDescriptor);
        Object.defineProperty(HTMLInputElement.prototype, 'type', {
            ...original,
            set(this: HTMLInputElement, value: string) {
                const changed = this.type !== value;

                original?.set?.call(this, value);

                if (changed) {
                    this.setSelectionRange(this.value.length, this.value.length);
                }
            },
        });
        onTestFinished(() => void restore());
        const user = userEvent.setup();
        const input = field() as HTMLInputElement;
        await user.type(input, 'abcdefgh');
        input.setSelectionRange(2, 5);

        await user.click(screen.getByRole('button', { name: 'Show password' }));

        expect(input).toHaveFocus();
        expect([input.selectionStart, input.selectionEnd]).toEqual([2, 5]);

        await user.keyboard('X');
        expect(input).toHaveValue('abXfgh');
    });

    it('leaves the focus on the eye when it is pressed with the keyboard, and still toggles', async () => {
        const user = userEvent.setup();
        const input = field();
        await user.type(input, 'secret');
        await user.tab();
        const eye = screen.getByRole('button', { name: 'Show password' });
        expect(eye).toHaveFocus();

        await user.keyboard('{Enter}');

        expect(input).toHaveAttribute('type', 'text');
        expect(screen.getByRole('button', { name: 'Hide password' })).toHaveFocus();

        await user.keyboard(' ');
        expect(input).toHaveAttribute('type', 'password');
    });

    it('does not submit the form when the eye is pressed', async () => {
        const onSubmit = vi.fn((event: { preventDefault: () => void }) => event.preventDefault());
        render(
            <form onSubmit={onSubmit}>
                <PasswordInput id="password" aria-label="Password" />
            </form>,
        );

        await userEvent.setup().click(screen.getByRole('button', { name: 'Show password' }));

        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('points the eye at its field, and stops the phone from changing what is typed', () => {
        const input = field();

        expect(screen.getByRole('button', { name: 'Show password' })).toHaveAttribute('aria-controls', 'password');
        expect(input).toHaveAttribute('autocapitalize', 'off');
        expect(input).toHaveAttribute('autocorrect', 'off');
        expect(input).toHaveAttribute('spellcheck', 'false');
    });

    it('accepts a pasted password and passes the autocomplete hint through for password managers', async () => {
        const user = userEvent.setup();
        render(<PasswordInput id="password" aria-label="Password" autoComplete="new-password" />);
        const input = screen.getByLabelText('Password');

        await user.click(input);
        await user.paste('pasted passphrase 123');

        expect(input).toHaveValue('pasted passphrase 123');
        expect(input).toHaveAttribute('autocomplete', 'new-password');
    });

    it('is disabled together with its eye', () => {
        render(<PasswordInput id="password" aria-label="Password" disabled />);

        expect(screen.getByLabelText('Password')).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Show password' })).toBeDisabled();
    });

    it('hands its input to a ref', () => {
        const ref = createRef<HTMLInputElement>();
        render(<PasswordInput id="password" aria-label="Password" ref={ref} />);

        expect(ref.current).toBe(screen.getByLabelText('Password'));
    });
});

describe('PasswordField', () => {
    it('has a visible label and ties its error to the field', () => {
        render(<PasswordField id="password" label="New password" required error="Use at least 12 characters" />);
        const input = screen.getByLabelText(/^New password/);

        expect(input).toBeRequired();
        expect(input).toBeInvalid();
        expect(input).toHaveAccessibleDescription('Use at least 12 characters');
        expect(screen.getByRole('alert')).toHaveTextContent('Use at least 12 characters');
    });

    it('has no error marking when there is no error', () => {
        render(<PasswordField id="password" label="Password" />);

        expect(screen.getByLabelText('Password')).not.toHaveAttribute('aria-invalid');
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
});

describe('PasswordRequirements', () => {
    it('says in words that the length is not met yet, and then that it is', () => {
        const { rerender } = render(<PasswordRequirements value={'a'.repeat(PASSWORD_MIN_LENGTH - 1)} />);

        expect(screen.getByRole('list', { name: 'Password requirements' })).toHaveTextContent(
            `At least ${PASSWORD_MIN_LENGTH} characters: not met yet`,
        );

        rerender(<PasswordRequirements value={'a'.repeat(PASSWORD_MIN_LENGTH)} />);

        expect(screen.getByRole('list', { name: 'Password requirements' })).toHaveTextContent(
            `At least ${PASSWORD_MIN_LENGTH} characters: met`,
        );
    });

    it('starts as not met for an empty password', () => {
        render(<PasswordRequirements value="" />);

        expect(screen.getByRole('list')).toHaveTextContent('not met yet');
    });
});
