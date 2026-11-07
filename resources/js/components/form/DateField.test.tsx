import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';
import { DateField } from '@/components/form/DateField';

function Box({ error }: { error?: string }) {
    const [value, setValue] = useState('');

    return (
        <DateField
            id="when"
            label="When"
            min="2026-01-01"
            max="2026-12-31"
            value={value}
            onChange={(event) => setValue(event.target.value)}
            error={error}
        />
    );
}

describe('DateField', () => {
    it("is the browser's own date input, with a label", () => {
        render(<Box />);

        const box = screen.getByLabelText('When');
        expect(box).toHaveAttribute('type', 'date');
    });

    it('takes a date as year, month, day, which is how the API writes it', async () => {
        render(<Box />);

        const box = screen.getByLabelText('When');
        await userEvent.type(box, '2026-06-15');

        expect(box).toHaveValue('2026-06-15');
    });

    it('passes the limits to the browser, so its calendar does not offer other days', () => {
        render(<Box />);

        expect(screen.getByLabelText('When')).toHaveAttribute('min', '2026-01-01');
        expect(screen.getByLabelText('When')).toHaveAttribute('max', '2026-12-31');
    });

    it('announces its error and links it to the box', () => {
        render(<Box error="Enter a real date" />);

        expect(screen.getByLabelText('When')).toHaveAccessibleDescription('Enter a real date');
        expect(screen.getByLabelText('When')).toBeInvalid();
    });
});
