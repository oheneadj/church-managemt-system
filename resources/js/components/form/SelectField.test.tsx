import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import { SelectField } from '@/components/form/SelectField';

const OPTIONS = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
] as const;

describe('SelectField', () => {
    it('lets the user choose an option by its label', async () => {
        render(<SelectField id="gender" label="Gender" options={OPTIONS} emptyLabel="Choose" />);

        await userEvent.selectOptions(screen.getByLabelText('Gender'), 'Female');

        expect(screen.getByLabelText('Gender')).toHaveValue('female');
    });

    it('starts on the empty choice when one is given', () => {
        render(<SelectField id="gender" label="Gender" options={OPTIONS} emptyLabel="Choose" />);

        expect(screen.getByLabelText('Gender')).toHaveValue('');
    });

    it('draws its own caret away from the border instead of the browser default', () => {
        render(<SelectField id="gender" label="Gender" options={OPTIONS} />);

        expect(screen.getByLabelText('Gender')).toHaveClass('appearance-none', 'pr-9');
    });

    it('shows the error and links it to the select', () => {
        render(<SelectField id="gender" label="Gender" options={OPTIONS} error="Choose a gender." />);

        expect(screen.getByLabelText('Gender')).toHaveAccessibleDescription('Choose a gender.');
        expect(screen.getByRole('alert')).toHaveTextContent('Choose a gender.');
    });
});
